<?php

namespace App\Datev;

use App\Enums\TaxTreatment;
use App\Models\DatevExport;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One booking per invoice and tax group: debtor against revenue account,
 * gross amount, S for invoices and H for credit notes.
 */
class DatevExporter
{
    public function __construct(private DatevExtfWriter $writer) {}

    public function export(CarbonInterface $from, CarbonInterface $to, ?int $userId = null): DatevExport
    {
        return DB::transaction(function () use ($from, $to, $userId): DatevExport {
            $invoices = Invoice::query()
                ->whereDate('issued_at', '>=', $from->toDateString())
                ->whereDate('issued_at', '<=', $to->toDateString())
                ->whereNull('datev_export_id')
                ->orderBy('issued_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($invoices->isEmpty()) {
                throw new RuntimeException(__('No unexported documents in this period.'));
            }

            $bookings = array_values($invoices->flatMap(fn (Invoice $invoice) => $this->bookingsFor($invoice))->all());

            $export = DatevExport::query()->create([
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'invoice_count' => $invoices->count(),
                'booking_count' => count($bookings),
                'path' => '',
                'sha256' => '',
                'user_id' => $userId,
            ]);

            $csv = $this->writer->write($bookings, $from, $to, 'Shop '.$from->format('d.m.Y').' - '.$to->format('d.m.Y'));
            $path = sprintf('datev/EXTF_Buchungsstapel_%s_%s_%d.csv', $from->format('Ymd'), $to->format('Ymd'), $export->id);

            Storage::disk('local')->put($path, $csv);

            $export->update(['path' => $path, 'sha256' => hash('sha256', $csv)]);

            Invoice::query()->whereKey($invoices->modelKeys())->update(['datev_export_id' => $export->id]);

            return $export;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bookingsFor(Invoice $invoice): array
    {
        $s = $invoice->snapshot;
        $treatment = TaxTreatment::from($s['tax_treatment']);
        $datev = config('shop.datev');
        $text = ($invoice->isCreditNote() ? 'Gutschrift ' : 'Rechnung ').$s['order']['number'].' '.($s['buyer']['company'] ?: $s['buyer']['name']);

        $lines = [];

        foreach (array_values($s['tax_groups']) as $i => $group) {
            if ($group['gross'] === 0) {
                continue;
            }

            $lines[] = [
                'Umsatz (ohne Soll/Haben-Kz)' => $group['gross'] / 100,
                'Soll/Haben-Kennzeichen' => $invoice->isCreditNote() ? 'H' : 'S',
                'WKZ Umsatz' => $invoice->currency,
                'Konto' => $datev['debtor_account'],
                'Gegenkonto (ohne BU-Schlüssel)' => $this->accountFor($treatment, (float) $group['rate']),
                'BU-Schlüssel' => '',
                'Belegdatum' => $invoice->issued_at->format('dm'),
                'Belegfeld 1' => mb_substr($invoice->number, 0, 36),
                'Belegfeld 2' => mb_substr((string) preg_replace('/\D+/', '', $s['order']['number']), 0, 12),
                'Buchungstext' => mb_substr($text, 0, 60),
                'EU-Land u. UStID (Bestimmung)' => match ($treatment) {
                    TaxTreatment::IntraCommunity, TaxTreatment::ReverseCharge => $s['buyer']['vat_id'] ?? '',
                    TaxTreatment::Oss => $s['buyer']['country'],
                    default => '',
                },
                'EU-Steuersatz (Bestimmung)' => $treatment === TaxTreatment::Oss && $group['rate'] > 0 ? $group['rate'] : '',
                'Festschreibung' => $datev['festschreibung'] ? 1 : 0,
                'Leistungsdatum' => now()->parse($s['service_date'])->format('dmY'),
                'Buchungs GUID' => $this->guid($invoice->id, $i),
            ];
        }

        return $lines;
    }

    private function accountFor(TaxTreatment $treatment, float $rate): string
    {
        $accounts = config('shop.datev.accounts');

        $key = match ($treatment) {
            TaxTreatment::IntraCommunity => 'intra_community',
            TaxTreatment::Export => 'export',
            TaxTreatment::ReverseCharge => 'reverse_charge',
            TaxTreatment::SmallBusiness => 'small_business',
            TaxTreatment::Oss => 'oss',
            TaxTreatment::Domestic, TaxTreatment::DistanceHome => match ($rate) {
                19.0 => 'domestic_19',
                7.0 => 'domestic_7',
                0.0 => 'domestic_0',
                default => throw new RuntimeException(__('No DATEV account mapped for domestic rate :rate%.', ['rate' => $rate])),
            },
        };

        return (string) $accounts[$key];
    }

    /**
     * Deterministic so a re-export is recognised as the same booking.
     */
    private function guid(int $invoiceId, int $groupIndex): string
    {
        $hex = md5(config('app.name').":invoice:{$invoiceId}:{$groupIndex}");

        return Str::lower(sprintf('%s-%s-4%s-a%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 13, 3), substr($hex, 17, 3), substr($hex, 20, 12)));
    }
}

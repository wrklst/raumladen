<?php

namespace App\Models;

use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An issued, immutable accounting document. Everything the PDF and the DATEV
 * export need lives in the snapshot; corrections are new credit notes.
 *
 * @property int $id
 * @property InvoiceType $type
 * @property string $number
 * @property int $order_id
 * @property int|null $references_invoice_id
 * @property Carbon $issued_at
 * @property string $currency
 * @property int $total_net_cents
 * @property int $total_tax_cents
 * @property int $total_gross_cents
 * @property array<string, mixed> $snapshot
 * @property string|null $pdf_path
 * @property int|null $datev_export_id
 */
#[Fillable([
    'type', 'number', 'order_id', 'references_invoice_id', 'issued_at', 'currency',
    'total_net_cents', 'total_tax_cents', 'total_gross_cents', 'snapshot', 'pdf_path',
])]
class Invoice extends Model
{
    /** @var list<string> */
    private const array MUTABLE_AFTER_ISSUE = ['datev_export_id', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            $dirty = array_keys($invoice->getDirty());

            $fillOnce = $invoice->getOriginal('pdf_path') === null ? ['pdf_path'] : [];
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_ISSUE, $fillOnce);

            if ($illegal !== []) {
                throw new LogicException('Issued documents are immutable: '.implode(', ', $illegal));
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Issued documents cannot be deleted.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function references(): BelongsTo
    {
        return $this->belongsTo(self::class, 'references_invoice_id');
    }

    public function datevExport(): BelongsTo
    {
        return $this->belongsTo(DatevExport::class);
    }

    public function isCreditNote(): bool
    {
        return $this->type === InvoiceType::CreditNote;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'issued_at' => 'date',
            'snapshot' => 'array',
        ];
    }
}

<?php

namespace App\Invoicing;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Countries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Issues invoices and credit notes as immutable snapshots. The number is
 * claimed inside the same transaction that stores the document.
 */
class InvoiceIssuer
{
    public function __construct(private InvoicePdf $pdf) {}

    public function issueInvoice(Order $order): Invoice
    {
        $existing = $order->invoices()->where('type', InvoiceType::Invoice)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($order): Invoice {
            $number = NumberSequence::nextFormatted('invoice');

            return $this->store($order, InvoiceType::Invoice, $number, null);
        });
    }

    /**
     * Full credit note (Gutschrift / Rechnungskorrektur) for a paid order.
     */
    public function issueCreditNote(Order $order): Invoice
    {
        $invoice = $order->invoices()->where('type', InvoiceType::Invoice)->first();

        if ($invoice === null) {
            throw new LogicException('Cannot credit an order without an invoice.');
        }

        $existing = $order->invoices()->where('type', InvoiceType::CreditNote)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $invoice): Invoice {
            $number = NumberSequence::nextFormatted('credit_note');

            return $this->store($order, InvoiceType::CreditNote, $number, $invoice);
        });
    }

    private function store(Order $order, InvoiceType $type, string $number, ?Invoice $references): Invoice
    {
        $snapshot = $this->snapshot($order, $type, $number, $references);

        $invoice = Invoice::query()->create([
            'type' => $type,
            'number' => $number,
            'order_id' => $order->id,
            'references_invoice_id' => $references?->id,
            'issued_at' => $snapshot['issued_at'],
            'currency' => $order->currency,
            'total_net_cents' => $snapshot['totals']['net'],
            'total_tax_cents' => $snapshot['totals']['tax'],
            'total_gross_cents' => $snapshot['totals']['gross'],
            'snapshot' => $snapshot,
        ]);

        $path = sprintf('invoices/%s/%s.pdf', now()->format('Y'), $number);
        Storage::disk(config('shop.invoice.disk'))->put($path, $this->pdf->render($invoice));
        $invoice->update(['pdf_path' => $path]);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Order $order, InvoiceType $type, string $number, ?Invoice $references): array
    {
        $order->loadMissing('items');
        $billing = $order->billing_address;
        $issuedAt = now()->toDateString();

        return [
            'type' => $type->value,
            'number' => $number,
            'issued_at' => $issuedAt,
            'service_date' => $order->paid_at?->toDateString() ?? $issuedAt,
            'due_date' => now()->addDays((int) config('shop.invoice.due_days'))->toDateString(),
            'references' => $references === null ? null : [
                'number' => $references->number,
                'issued_at' => $references->issued_at->toDateString(),
            ],
            'order' => ['number' => $order->number, 'uuid' => $order->uuid, 'placed_at' => $order->created_at?->toDateTimeString()],
            'seller' => config('shop.seller'),
            'buyer' => [
                'name' => $order->customerName(),
                'company' => $billing['company'] ?? null,
                'address1' => $billing['address1'] ?? null,
                'address2' => $billing['address2'] ?? null,
                'zip' => $billing['zip'] ?? null,
                'city' => $billing['city'] ?? null,
                'country' => $billing['country'] ?? $order->country,
                'country_name' => Countries::name($billing['country'] ?? $order->country, $order->locale),
                'email' => $order->email,
                'vat_id' => $order->vat_id_valid ? $order->vat_id : null,
            ],
            'shipping_address' => $order->shipping_address,
            'locale' => $order->locale,
            'currency' => $order->currency,
            'prices_include_tax' => $order->prices_include_tax,
            'tax_treatment' => $order->tax_treatment->value,
            'tax_category' => $order->tax_treatment->category(),
            'tax_note' => $order->tax_note,
            'lines' => $order->items->values()->map(fn (OrderItem $item, int $i) => [
                'position' => $i + 1,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'discount_cents' => $item->discount_cents,
                'tax_rate' => $item->tax_rate,
                'tax_category' => $item->tax_category,
                'net' => $item->line_net_cents,
                'tax' => $item->line_tax_cents,
                'gross' => $item->line_gross_cents,
            ])->all(),
            'shipping' => $order->shipping_net_cents + $order->shipping_tax_cents === 0 ? null : [
                'net' => $order->shipping_net_cents,
                'tax' => $order->shipping_tax_cents,
                'gross' => $order->shipping_net_cents + $order->shipping_tax_cents,
                'tax_rate' => $order->shipping_tax_rate,
            ],
            'coupon' => $order->coupon_code === null ? null : ['code' => $order->coupon_code, 'discount_cents' => $order->discount_cents],
            'tax_groups' => $order->tax_summary ?? [],
            'totals' => [
                'net' => $order->subtotal_net_cents + $order->shipping_net_cents,
                'tax' => $order->tax_cents,
                'gross' => $order->total_cents,
            ],
            'payment' => [
                'gateway' => $order->payment_gateway,
                'paid_at' => $order->paid_at?->toDateTimeString(),
            ],
        ];
    }
}

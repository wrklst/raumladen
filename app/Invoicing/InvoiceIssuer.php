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
     * Credit note (Gutschrift / Rechnungskorrektur) for a paid order. Without
     * quantities it credits everything not yet credited and returns the latest
     * existing credit note when nothing is left (idempotent). Otherwise
     * $quantities maps order item ids to the units to credit.
     *
     * @param  array<int, int>|null  $quantities
     */
    public function issueCreditNote(Order $order, ?array $quantities = null, bool $includeShipping = true): Invoice
    {
        $invoice = $order->invoices()->where('type', InvoiceType::Invoice)->first();

        if ($invoice === null) {
            throw new LogicException('Cannot credit an order without an invoice.');
        }

        return DB::transaction(function () use ($order, $invoice, $quantities, $includeShipping): Invoice {
            Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->unsetRelation('invoices')->load('items', 'invoices');

            $credit = $this->creditFor($order, $quantities, $includeShipping);

            if ($credit === null) {
                if ($quantities !== null) {
                    throw new LogicException('Nothing to credit.');
                }

                return $order->invoices->where('type', InvoiceType::CreditNote)->sortByDesc('id')->first()
                    ?? throw new LogicException('Nothing to credit.');
            }

            $creditNote = $this->store($order, InvoiceType::CreditNote, NumberSequence::nextFormatted('credit_note'), $invoice, $credit);

            foreach ($credit['lines'] as $line) {
                $line['item']->increment('refunded_quantity', $line['quantity']);
            }

            return $creditNote;
        });
    }

    /**
     * Amounts of one credit note. Each amount is the difference of the rounded
     * cumulative share before and after, so crediting all units in any number of
     * steps sums exactly to the original line.
     *
     * @param  array<int, int>|null  $quantities
     * @return array{lines: list<array{item: OrderItem, quantity: int, net: int, tax: int, gross: int, discount: int}>, shipping: array{net: int, tax: int, gross: int, rate: float}|null, whole: bool}|null
     */
    private function creditFor(Order $order, ?array $quantities, bool $includeShipping): ?array
    {
        $hadCredit = $order->invoices->contains(fn (Invoice $invoice) => $invoice->isCreditNote());
        $lines = [];

        foreach ($order->items as $item) {
            $quantity = $quantities === null ? $item->refundableQuantity() : ($quantities[$item->id] ?? 0);

            if ($quantity > $item->refundableQuantity()) {
                throw new LogicException("Cannot credit more than the refundable quantity of item {$item->id}.");
            }

            if ($quantity < 1) {
                continue;
            }

            $share = fn (int $total): int => $this->cumulative($total, $item->quantity, $item->refunded_quantity + $quantity)
                - $this->cumulative($total, $item->quantity, $item->refunded_quantity);
            $net = $share($item->line_net_cents);
            $tax = $share($item->line_tax_cents);

            $lines[] = ['item' => $item, 'quantity' => $quantity, 'net' => $net, 'tax' => $tax, 'gross' => $net + $tax, 'discount' => $share($item->discount_cents)];
        }

        $shippingGross = $order->shipping_net_cents + $order->shipping_tax_cents;
        $withShipping = $includeShipping && $shippingGross > 0 && ! $order->shippingCredited();

        if ($lines === [] && ! $withShipping) {
            return null;
        }

        $shipping = $withShipping
            ? ['net' => $order->shipping_net_cents, 'tax' => $order->shipping_tax_cents, 'gross' => $shippingGross, 'rate' => $order->shipping_tax_rate]
            : null;

        // Crediting the complete untouched order reproduces the original totals as before.
        $whole = ! $hadCredit
            && count($lines) === $order->items->count()
            && collect($lines)->every(fn (array $line) => $line['quantity'] === $line['item']->quantity)
            && ($shippingGross === 0 || $withShipping);

        return ['lines' => $lines, 'shipping' => $shipping, 'whole' => $whole];
    }

    private function cumulative(int $total, int $quantity, int $units): int
    {
        return intdiv(2 * $total * $units + $quantity, 2 * $quantity);
    }

    /**
     * @param  array{lines: list<array{item: OrderItem, quantity: int, net: int, tax: int, gross: int, discount: int}>, shipping: array{net: int, tax: int, gross: int, rate: float}|null, whole: bool}|null  $credit
     */
    private function store(Order $order, InvoiceType $type, string $number, ?Invoice $references, ?array $credit = null): Invoice
    {
        $snapshot = $this->snapshot($order, $type, $number, $references);

        if ($credit !== null && ! $credit['whole']) {
            $snapshot = [...$snapshot, ...$this->partialAmounts($order, $credit)];
        }

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
     * The snapshot keys that differ for a partial credit note.
     *
     * @param  array{lines: list<array{item: OrderItem, quantity: int, net: int, tax: int, gross: int, discount: int}>, shipping: array{net: int, tax: int, gross: int, rate: float}|null, whole: bool}  $credit
     * @return array<string, mixed>
     */
    private function partialAmounts(Order $order, array $credit): array
    {
        $groups = [];
        $totals = ['net' => 0, 'tax' => 0, 'gross' => 0];
        $discount = 0;

        $add = function (float $rate, int $net, int $tax, int $gross) use (&$groups, &$totals, $order): void {
            $key = number_format($rate, 2, '.', '');
            $groups[$key] ??= ['rate' => $rate, 'category' => $order->tax_treatment->category(), 'net' => 0, 'tax' => 0, 'gross' => 0];
            $groups[$key]['net'] += $net;
            $groups[$key]['tax'] += $tax;
            $groups[$key]['gross'] += $gross;
            $totals['net'] += $net;
            $totals['tax'] += $tax;
            $totals['gross'] += $gross;
        };

        $lines = [];

        foreach ($credit['lines'] as $i => $line) {
            $item = $line['item'];
            $discount += $line['discount'];
            $add($item->tax_rate, $line['net'], $line['tax'], $line['gross']);
            $lines[] = [
                'position' => $i + 1,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $line['quantity'],
                'unit_price_cents' => $item->unit_price_cents,
                'discount_cents' => $line['discount'],
                'tax_rate' => $item->tax_rate,
                'tax_category' => $item->tax_category,
                'net' => $line['net'],
                'tax' => $line['tax'],
                'gross' => $line['gross'],
            ];
        }

        if ($credit['shipping'] !== null) {
            $add($credit['shipping']['rate'], $credit['shipping']['net'], $credit['shipping']['tax'], $credit['shipping']['gross']);
        }

        krsort($groups, SORT_NUMERIC);

        return [
            'lines' => $lines,
            'shipping' => $credit['shipping'],
            'coupon' => $order->coupon_code === null || $discount === 0 ? null : ['code' => $order->coupon_code, 'discount_cents' => $discount],
            'tax_groups' => array_values($groups),
            'totals' => $totals,
        ];
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

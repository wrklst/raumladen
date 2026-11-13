<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->number,
            'status' => $this->status->value,
            'locale' => $this->locale,
            'currency' => $this->currency,
            'email' => $this->email,
            'customer_name' => $this->customerName(),
            'customer_group_code' => $this->customer_group_code,
            'prices_include_tax' => $this->prices_include_tax,
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'country_name' => Countries::name($this->country),
            'vat_id' => $this->vat_id,
            'vat_id_valid' => $this->vat_id_valid,
            'tax_treatment' => $this->tax_treatment->value,
            'tax_note' => $this->tax_note,
            'coupon_code' => $this->coupon_code,
            'subtotal_net_cents' => $this->subtotal_net_cents,
            'discount_cents' => $this->discount_cents,
            'shipping_net_cents' => $this->shipping_net_cents,
            'shipping_tax_cents' => $this->shipping_tax_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'tax_summary' => $this->tax_summary ?? [],
            'payment_gateway' => $this->payment_gateway,
            'customer_note' => $this->customer_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'refunded_quantity' => $item->refunded_quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'tax_rate' => $item->tax_rate,
                'discount_cents' => $item->discount_cents,
                'line_net_cents' => $item->line_net_cents,
                'line_tax_cents' => $item->line_tax_cents,
                'line_gross_cents' => $item->line_gross_cents,
            ])),
            'shipping_refunded' => $this->whenLoaded('invoices', fn () => $this->shippingCredited()),
            'invoices' => $this->whenLoaded('invoices', fn () => $this->invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'type' => $invoice->type->value,
                'issued_at' => $invoice->issued_at->toDateString(),
                'total_gross_cents' => $invoice->total_gross_cents,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'gateway' => $payment->gateway,
                'gateway_id' => $payment->gateway_id,
                'status' => $payment->status->value,
                'method' => $payment->method,
                'amount_cents' => $payment->amount_cents,
                'refunded_cents' => $payment->refunded_cents,
                'created_at' => $payment->created_at?->toIso8601String(),
            ])),
        ];
    }
}

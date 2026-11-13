<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class OrderRefundRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
            'include_shipping' => ['boolean'],
            'restock' => ['boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $order = $this->route('order');

            if (! $order instanceof Order) {
                return;
            }

            $order->load('items', 'invoices');

            if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Shipped], true)) {
                $validator->errors()->add('order', 'Only a paid order can be partially refunded.');

                return;
            }

            $selected = false;

            foreach ($this->creditQuantities() as $itemId => $quantity) {
                $item = $order->items->firstWhere('id', $itemId);

                if ($item === null || $quantity > $item->refundableQuantity()) {
                    $validator->errors()->add("quantities.{$itemId}", 'Quantity exceeds what is still refundable.');
                }

                $selected = true;
            }

            $shippingOpen = ! $order->shippingCredited();

            if ($this->boolean('include_shipping') && ! $shippingOpen) {
                $validator->errors()->add('include_shipping', 'Shipping has already been credited.');
            }

            if (! $selected && ! ($this->boolean('include_shipping') && $shippingOpen)) {
                $validator->errors()->add('quantities', 'Select at least one item or the shipping.');
            }
        }];
    }

    /**
     * Positive quantities keyed by order item id.
     *
     * @return array<int, int>
     */
    public function creditQuantities(): array
    {
        return collect(Arr::wrap($this->validated('quantities', [])))
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn (int $quantity) => $quantity > 0)
            ->all();
    }
}

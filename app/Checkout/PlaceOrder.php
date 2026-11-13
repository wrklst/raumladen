<?php

namespace App\Checkout;

use App\Cart\CartCalculator;
use App\Cart\CartLine;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\NumberSequence;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlaceOrder
{
    public function __construct(private CartCalculator $calculator) {}

    /**
     * @param  Collection<int, CartLine>  $cartLines
     * @param  array{email: string, billing_address: array<string, string|null>, shipping_address: array<string, string|null>|null, vat_id: string|null, vat_id_valid: bool, customer_note: string|null, locale: string}  $data
     */
    public function handle(Collection $cartLines, ?User $user, CustomerGroup $group, array $data, ?Coupon $coupon): Order
    {
        return DB::transaction(function () use ($cartLines, $user, $group, $data, $coupon): Order {
            $products = Product::query()->whereKey($cartLines->pluck('product.id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($cartLines as $line) {
                $product = $products->get($line->product->id);

                if ($product === null || ! $product->hasStockFor($line->quantity)) {
                    throw new OutOfStockException($line->product);
                }
            }

            $shipping = $data['shipping_address'] ?? $data['billing_address'];

            $totals = $this->calculator->calculate($cartLines, $group, $shipping['country'], $data['vat_id_valid'], $coupon);

            $user ??= User::query()->firstOrCreate(['email' => $data['email']], [
                'name' => trim($data['billing_address']['first_name'].' '.$data['billing_address']['last_name']),
                'role' => UserRole::Customer,
                'customer_group_id' => $group->id,
                'locale' => $data['locale'],
            ]);

            $order = Order::query()->create([
                'number' => NumberSequence::nextFormatted('order'),
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'locale' => $data['locale'],
                'currency' => config('shop.currency'),
                'email' => $data['email'],
                'customer_group_code' => $group->code,
                'prices_include_tax' => $totals->pricesIncludeTax,
                'billing_address' => $data['billing_address'],
                'shipping_address' => $shipping,
                'country' => $totals->country,
                'vat_id' => $data['vat_id'],
                'vat_id_valid' => $data['vat_id_valid'],
                'tax_treatment' => $totals->treatment,
                'tax_note' => $totals->treatment->note($data['locale']),
                'coupon_id' => $totals->coupon?->id,
                'coupon_code' => $totals->coupon?->code,
                'subtotal_net_cents' => $totals->subtotalNetCents,
                'discount_cents' => $totals->discountCents,
                'shipping_net_cents' => $totals->shippingNetCents,
                'shipping_tax_cents' => $totals->shippingTaxCents,
                'shipping_tax_rate' => $totals->shippingTaxRate,
                'tax_cents' => $totals->taxCents,
                'total_cents' => $totals->totalCents,
                'tax_summary' => $totals->taxSummary,
                'customer_note' => $data['customer_note'],
            ]);

            $order->items()->createMany(array_map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'name' => $line['name'],
                'sku' => $line['sku'],
                'quantity' => $line['quantity'],
                'unit_price_cents' => $line['unit_price_cents'],
                'tax_class' => $line['tax_class'],
                'tax_rate' => $line['tax_rate'],
                'tax_category' => $line['tax_category'],
                'discount_cents' => $line['discount_cents'],
                'line_net_cents' => $line['line_net_cents'],
                'line_tax_cents' => $line['line_tax_cents'],
                'line_gross_cents' => $line['line_gross_cents'],
            ], $totals->lines));

            foreach ($cartLines as $line) {
                if ($products[$line->product->id]->track_stock) {
                    $products[$line->product->id]->decrement('stock', $line->quantity);
                }
            }

            $totals->coupon?->increment('uses');

            return $order;
        });
    }
}

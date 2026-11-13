<?php

namespace App\Cart;

use App\Enums\TaxClass;
use App\Enums\TaxTreatment;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Support\Countries;
use App\Tax\TaxMath;
use App\Tax\TaxResolver;
use Illuminate\Support\Collection;

/**
 * Prices are stored gross including German VAT of the product's tax class.
 * The net price is derived from that and the destination rate is applied.
 * Consumer groups see and pay gross amounts; business groups see net amounts.
 * Rounding happens per line in the customer's price basis.
 */
class CartCalculator
{
    public function __construct(private TaxResolver $taxResolver) {}

    /**
     * @param  Collection<int, CartLine>  $cartLines
     */
    public function calculate(
        Collection $cartLines,
        ?CustomerGroup $group,
        string $country,
        bool $vatIdValid = false,
        ?Coupon $coupon = null,
    ): CartTotals {
        $home = config('shop.tax.home_country');
        $country = strtoupper($country ?: $home);
        $pricesIncludeTax = $group?->prices_include_tax ?? true;
        $digitalOnly = $cartLines->isNotEmpty() && $cartLines->every(fn (CartLine $line) => $line->product->isDigital());

        $estimatedNet = $cartLines->sum(fn (CartLine $line) => (int) round(
            TaxMath::netOf($line->product->priceFor($group), $line->product->tax_class->rateIn($home)) * $line->quantity
        ));

        $treatment = $this->taxResolver->resolve($country, $vatIdValid, $digitalOnly, $estimatedNet);
        $rateCountry = $treatment->rateCountry($country);

        $lines = [];

        foreach ($cartLines as $cartLine) {
            $product = $cartLine->product;
            $rate = $rateCountry === null ? 0.0 : $product->tax_class->rateIn($rateCountry);
            $netUnit = TaxMath::netOf($product->priceFor($group), $product->tax_class->rateIn($home));
            $unitDisplay = (int) round($pricesIncludeTax ? $netUnit * (1 + $rate / 100) : $netUnit);

            $lines[] = [
                'product_id' => $product->id,
                'name' => $product->translate('title'),
                'sku' => $product->sku,
                'quantity' => $cartLine->quantity,
                'unit_price_cents' => $unitDisplay,
                'tax_class' => $product->tax_class->value,
                'tax_rate' => $rate,
                'tax_category' => $treatment->category(),
                'line_display_cents' => $unitDisplay * $cartLine->quantity,
                'image' => $product->imageUrl(),
                'slug' => $product->translate('slug'),
                'url' => $product->urlFor(app()->getLocale()),
                'digital' => $product->isDigital(),
            ];
        }

        $subtotalDisplay = array_sum(array_column($lines, 'line_display_cents'));

        [$coupon, $couponError, $discount] = $this->applyCoupon($coupon, $subtotalDisplay, $group);

        $discounts = TaxMath::distribute($discount, array_column($lines, 'line_display_cents'));

        foreach ($lines as $i => $line) {
            $split = TaxMath::split($line['line_display_cents'] - $discounts[$i], $line['tax_rate'], $pricesIncludeTax);
            $lines[$i]['discount_cents'] = $discounts[$i];
            $lines[$i]['line_net_cents'] = $split['net'];
            $lines[$i]['line_tax_cents'] = $split['tax'];
            $lines[$i]['line_gross_cents'] = $split['gross'];
        }

        $shipping = $this->shipping($lines, $country, $digitalOnly, $subtotalDisplay - $discount, $treatment, $rateCountry, $pricesIncludeTax);

        return new CartTotals(
            lines: $lines,
            treatment: $treatment,
            pricesIncludeTax: $pricesIncludeTax,
            country: $country,
            vatIdValid: $vatIdValid,
            subtotalDisplayCents: $subtotalDisplay,
            subtotalNetCents: array_sum(array_column($lines, 'line_net_cents')),
            discountCents: $discount,
            coupon: $coupon,
            couponError: $couponError,
            shippingDisplayCents: $shipping['display'],
            shippingNetCents: $shipping['net'],
            shippingTaxCents: $shipping['tax'],
            shippingTaxRate: $shipping['rate'],
            taxCents: array_sum(array_column($lines, 'line_tax_cents')) + $shipping['tax'],
            totalCents: array_sum(array_column($lines, 'line_gross_cents')) + $shipping['gross'],
            taxSummary: $this->summarize($lines, $shipping, $treatment),
        );
    }

    /**
     * @return array{0: Coupon|null, 1: string|null, 2: int}
     */
    private function applyCoupon(?Coupon $coupon, int $subtotalDisplay, ?CustomerGroup $group): array
    {
        if ($coupon === null) {
            return [null, null, 0];
        }

        $reason = $coupon->rejectionReason($subtotalDisplay, $group);

        if ($reason !== null) {
            return [null, $reason, 0];
        }

        return [$coupon, null, $coupon->discountFor($subtotalDisplay)];
    }

    /**
     * Shipping follows the tax rate of the largest line (ancillary service).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{display: int, net: int, tax: int, gross: int, rate: float}
     */
    private function shipping(array $lines, string $country, bool $digitalOnly, int $basisAfterDiscount, TaxTreatment $treatment, ?string $rateCountry, bool $pricesIncludeTax): array
    {
        $none = ['display' => 0, 'net' => 0, 'tax' => 0, 'gross' => 0, 'rate' => 0.0];

        if ($lines === [] || $digitalOnly) {
            return $none;
        }

        $zone = collect(config('shop.shipping'))->first(fn (array $zone) => match (true) {
            $zone['countries'] === '*' => true,
            $zone['countries'] === 'eu' => Countries::isEu($country),
            default => in_array($country, $zone['countries'], true),
        });

        if ($zone === null || ($zone['free_from_cents'] !== null && $basisAfterDiscount >= $zone['free_from_cents'])) {
            return $none;
        }

        $largest = collect($lines)->sortByDesc('line_net_cents')->first();
        $class = TaxClass::from($largest['tax_class']);
        $rate = $rateCountry === null ? 0.0 : $class->rateIn($rateCountry);
        $netShipping = TaxMath::netOf($zone['price_cents'], $class->rateIn(config('shop.tax.home_country')));
        $display = (int) round($pricesIncludeTax ? $netShipping * (1 + $rate / 100) : $netShipping);

        return ['display' => $display, 'rate' => $rate, ...TaxMath::split($display, $rate, $pricesIncludeTax)];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array{net: int, tax: int, gross: int, rate: float}  $shipping
     * @return list<array{rate: float, category: string, net: int, tax: int, gross: int}>
     */
    private function summarize(array $lines, array $shipping, TaxTreatment $treatment): array
    {
        $groups = [];

        $add = function (float $rate, int $net, int $tax, int $gross) use (&$groups, $treatment): void {
            $key = number_format($rate, 2, '.', '');
            $groups[$key] ??= ['rate' => $rate, 'category' => $treatment->category(), 'net' => 0, 'tax' => 0, 'gross' => 0];
            $groups[$key]['net'] += $net;
            $groups[$key]['tax'] += $tax;
            $groups[$key]['gross'] += $gross;
        };

        foreach ($lines as $line) {
            $add($line['tax_rate'], $line['line_net_cents'], $line['line_tax_cents'], $line['line_gross_cents']);
        }

        if ($shipping['gross'] > 0) {
            $add($shipping['rate'], $shipping['net'], $shipping['tax'], $shipping['gross']);
        }

        krsort($groups, SORT_NUMERIC);

        return array_values($groups);
    }
}

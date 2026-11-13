<?php

use App\Cart\CartCalculator;
use App\Cart\CartLine;
use App\Cart\CartTotals;
use App\Enums\TaxTreatment;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;

beforeEach(function () {
    // Coupon mechanics are tested without the fixed-book-price rule; FixedBookPriceTest covers the rule.
    config(['shop.fixed_price_countries' => []]);
    config(['shop.tax.small_business' => false, 'shop.tax.oss_registered' => false]);
    $this->consumer = CustomerGroup::factory()->consumer()->create();
    $this->travelTo('2026-06-15 12:00:00');
});

/**
 * @param  list<array{0: Product, 1: int}>|Product  $items
 */
function calculateCart(array|Product $items, ?CustomerGroup $group, string $country = 'DE', bool $vatIdValid = false, ?Coupon $coupon = null): CartTotals
{
    $items = $items instanceof Product ? [[$items, 1]] : $items;
    $lines = collect($items)->map(fn (array $item) => new CartLine($item[0], $item[1]));

    return app(CartCalculator::class)->calculate($lines, $group, $country, $vatIdValid, $coupon);
}

test('a domestic order is charged German VAT inclusive of the price', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $this->consumer);

    expect($totals->treatment)->toBe(TaxTreatment::Domestic)
        ->and($totals->pricesIncludeTax)->toBeTrue()
        ->and($totals->subtotalDisplayCents)->toBe(2000)
        ->and($totals->subtotalNetCents)->toBe(1869)
        ->and($totals->shippingDisplayCents)->toBe(390)
        ->and($totals->shippingNetCents)->toBe(364)
        ->and($totals->shippingTaxCents)->toBe(26)
        ->and($totals->shippingTaxRate)->toBe(7.0)
        ->and($totals->taxCents)->toBe(131 + 26)
        ->and($totals->totalCents)->toBe(2390)
        ->and($totals->lines[0])->toMatchArray([
            'unit_price_cents' => 2000, 'tax_rate' => 7.0, 'tax_category' => 'S',
            'line_net_cents' => 1869, 'line_tax_cents' => 131, 'line_gross_cents' => 2000,
        ]);
});

test('quantities multiply the unit price', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart([[$product, 3]], $this->consumer);

    expect($totals->lines[0])->toMatchArray(['quantity' => 3, 'line_gross_cents' => 6000, 'line_net_cents' => 5607, 'line_tax_cents' => 393])
        ->and($totals->shippingDisplayCents)->toBe(0)
        ->and($totals->totalCents)->toBe(6000);
});

test('an EU consumer below the threshold pays the home rate with EU shipping', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $this->consumer, 'AT');

    expect($totals->treatment)->toBe(TaxTreatment::DistanceHome)
        ->and($totals->lines[0]['tax_rate'])->toBe(7.0)
        ->and($totals->shippingDisplayCents)->toBe(990)
        ->and($totals->shippingNetCents)->toBe(925)
        ->and($totals->shippingTaxCents)->toBe(65)
        ->and($totals->totalCents)->toBe(2990)
        ->and($totals->taxCents)->toBe(196);
});

test('the destination rate applies once previous sales crossed the threshold', function () {
    Order::factory()->create([
        'tax_treatment' => TaxTreatment::DistanceHome,
        'subtotal_net_cents' => 1_000_001,
        'created_at' => now()->subYear(),
    ]);
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $this->consumer, 'AT');

    expect($totals->treatment)->toBe(TaxTreatment::Oss)
        ->and($totals->lines[0])->toMatchArray(['tax_rate' => 10.0, 'unit_price_cents' => 2056, 'line_net_cents' => 1869, 'line_tax_cents' => 187])
        ->and($totals->shippingDisplayCents)->toBe(1018)
        ->and($totals->totalCents)->toBe(2056 + 1018)
        ->and($totals->taxCents)->toBe(187 + 93);
});

test('the sale that crosses the threshold is already taxed at the destination rate', function (int $priceCents, TaxTreatment $expected, float $rate) {
    $product = Product::factory()->create(['price_cents' => $priceCents]);

    $totals = calculateCart($product, $this->consumer, 'AT');

    expect($totals->treatment)->toBe($expected)->and($totals->lines[0]['tax_rate'])->toBe($rate);
})->with([
    'net 934,579 is below the limit' => [1_000_000, TaxTreatment::DistanceHome, 7.0],
    'net 1,028,037 crosses the limit' => [1_100_000, TaxTreatment::Oss, 10.0],
]);

test('an OSS-registered seller charges destination VAT on every EU consumer sale', function (string $country, int $unit, int $net, int $tax, int $shipping, int $shippingTax) {
    config(['shop.tax.oss_registered' => true]);
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $this->consumer, $country);

    expect($totals->treatment)->toBe(TaxTreatment::Oss)
        ->and($totals->lines[0])->toMatchArray(['unit_price_cents' => $unit, 'line_net_cents' => $net, 'line_tax_cents' => $tax, 'tax_category' => 'S'])
        ->and($totals->shippingDisplayCents)->toBe($shipping)
        ->and($totals->shippingTaxCents)->toBe($shippingTax)
        ->and($totals->totalCents)->toBe($unit + $shipping)
        ->and($totals->taxCents)->toBe($tax + $shippingTax);
})->with([
    'Austria reduced 10 %' => ['AT', 2056, 1869, 187, 1018, 93],
    'France reduced 5.5 %' => ['FR', 1972, 1869, 103, 976, 51],
]);

test('an OSS sale of a standard-rated product uses the standard rate of the destination', function () {
    config(['shop.tax.oss_registered' => true]);
    $product = Product::factory()->standardRate()->create(['price_cents' => 1190]);

    $totals = calculateCart($product, $this->consumer, 'AT');

    expect($totals->lines[0])->toMatchArray(['tax_rate' => 20.0, 'unit_price_cents' => 1200, 'line_net_cents' => 1000, 'line_tax_cents' => 200]);
});

test('an EU business with a valid VAT ID pays net prices without tax', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $this->consumer, 'AT', vatIdValid: true);

    expect($totals->treatment)->toBe(TaxTreatment::IntraCommunity)
        ->and($totals->lines[0])->toMatchArray(['unit_price_cents' => 1869, 'tax_rate' => 0.0, 'tax_category' => 'K', 'line_net_cents' => 1869, 'line_tax_cents' => 0, 'line_gross_cents' => 1869])
        ->and($totals->shippingNetCents)->toBe(925)
        ->and($totals->shippingTaxCents)->toBe(0)
        ->and($totals->taxCents)->toBe(0)
        ->and($totals->totalCents)->toBe(1869 + 925)
        ->and($totals->taxSummary)->toBe([['rate' => 0.0, 'category' => 'K', 'net' => 2794, 'tax' => 0, 'gross' => 2794]]);
});

test('a third country export is tax free', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $this->consumer, 'CH');

    expect($totals->treatment)->toBe(TaxTreatment::Export)
        ->and($totals->lines[0])->toMatchArray(['unit_price_cents' => 1869, 'tax_category' => 'G', 'line_tax_cents' => 0])
        ->and($totals->shippingDisplayCents)->toBe(1860)
        ->and($totals->taxCents)->toBe(0)
        ->and($totals->totalCents)->toBe(1869 + 1860);
});

test('a small business charges no VAT at all', function (string $country) {
    config(['shop.tax.small_business' => true]);
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $this->consumer, $country);

    expect($totals->treatment)->toBe(TaxTreatment::SmallBusiness)
        ->and($totals->lines[0])->toMatchArray(['tax_rate' => 0.0, 'tax_category' => 'E', 'line_tax_cents' => 0])
        ->and($totals->taxCents)->toBe(0)
        ->and($totals->totalCents)->toBe($totals->subtotalNetCents + $totals->shippingNetCents);
})->with(['DE', 'AT', 'CH']);

test('a business group sees net prices and pays tax on top', function () {
    $business = CustomerGroup::factory()->business()->create();
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $business);

    expect($totals->pricesIncludeTax)->toBeFalse()
        ->and($totals->lines[0])->toMatchArray(['unit_price_cents' => 1869, 'line_net_cents' => 1869, 'line_tax_cents' => 131, 'line_gross_cents' => 2000])
        ->and($totals->subtotalDisplayCents)->toBe(1869)
        ->and($totals->shippingDisplayCents)->toBe(364)
        ->and($totals->shippingTaxCents)->toBe(25)
        ->and($totals->taxCents)->toBe(131 + 25)
        ->and($totals->totalCents)->toBe(2000 + 389);
});

test('a group-specific price replaces the list price', function () {
    $business = CustomerGroup::factory()->business()->create();
    $product = Product::factory()->create(['price_cents' => 2000]);
    ProductPrice::query()->create(['product_id' => $product->id, 'customer_group_id' => $business->id, 'price_cents' => 1500]);

    $b2b = calculateCart($product->refresh(), $business);
    $b2c = calculateCart($product->refresh(), $this->consumer);

    expect($b2b->lines[0])->toMatchArray(['unit_price_cents' => 1402, 'line_tax_cents' => 98, 'line_gross_cents' => 1500])
        ->and($b2c->lines[0]['unit_price_cents'])->toBe(2000);
});

test('a business group with a valid VAT ID pays the net price for intra-community supplies', function () {
    $business = CustomerGroup::factory()->business()->create();
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $business, 'AT', vatIdValid: true);

    expect($totals->treatment)->toBe(TaxTreatment::IntraCommunity)
        ->and($totals->lines[0])->toMatchArray(['unit_price_cents' => 1869, 'line_tax_cents' => 0, 'line_gross_cents' => 1869])
        ->and($totals->taxCents)->toBe(0);
});

test('a percentage coupon is spread across the lines and the totals still add up', function () {
    $reduced = Product::factory()->create(['price_cents' => 1000]);
    $standard = Product::factory()->standardRate()->create(['price_cents' => 1900]);
    $coupon = Coupon::factory()->create(['value' => 10]);

    $totals = calculateCart([[$reduced, 1], [$standard, 1]], $this->consumer, coupon: $coupon);

    expect($totals->discountCents)->toBe(290)
        ->and($totals->coupon?->is($coupon))->toBeTrue()
        ->and($totals->couponError)->toBeNull()
        ->and($totals->lines[0])->toMatchArray(['discount_cents' => 100, 'line_gross_cents' => 900, 'line_net_cents' => 841, 'line_tax_cents' => 59])
        ->and($totals->lines[1])->toMatchArray(['discount_cents' => 190, 'line_gross_cents' => 1710, 'line_net_cents' => 1437, 'line_tax_cents' => 273])
        ->and($totals->shippingDisplayCents)->toBe(390)
        ->and($totals->shippingTaxRate)->toBe(19.0)
        ->and($totals->totalCents)->toBe(900 + 1710 + 390)
        ->and($totals->taxCents)->toBe(59 + 273 + 62);
});

test('a fixed coupon that does not divide evenly is distributed to the cent', function () {
    $products = Product::factory()->count(3)->create(['price_cents' => 1000]);
    $coupon = Coupon::factory()->fixed(101)->create();

    $totals = calculateCart($products->map(fn (Product $p) => [$p, 1])->all(), $this->consumer, coupon: $coupon);

    $discounts = array_column($totals->lines, 'discount_cents');

    expect(array_sum($discounts))->toBe(101)
        ->and(max($discounts) - min($discounts))->toBeLessThanOrEqual(1)
        ->and(array_sum(array_column($totals->lines, 'line_gross_cents')) + $totals->shippingDisplayCents)->toBe($totals->totalCents)
        ->and($totals->totalCents)->toBe(3000 - 101 + 390);
});

test('a coupon that is not applicable is reported and ignored', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);
    $coupon = Coupon::factory()->create(['min_order_cents' => 5000]);

    $totals = calculateCart($product, $this->consumer, coupon: $coupon);

    expect($totals->coupon)->toBeNull()
        ->and($totals->couponError)->toBe('below_minimum')
        ->and($totals->discountCents)->toBe(0)
        ->and($totals->totalCents)->toBe(2390);
});

test('shipping is waived once the discounted subtotal reaches the free-shipping threshold', function (int $couponValue, int $shipping) {
    $product = Product::factory()->create(['price_cents' => 5000]);
    $coupon = $couponValue === 0 ? null : Coupon::factory()->create(['value' => $couponValue]);

    $totals = calculateCart($product, $this->consumer, coupon: $coupon);

    expect($totals->shippingDisplayCents)->toBe($shipping);
})->with([
    'exactly at the threshold' => [0, 0],
    'discount drops below the threshold' => [10, 390],
]);

test('shipping zones decide the price and the EU threshold', function (string $country, int $price, int $shipping) {
    $product = Product::factory()->create(['price_cents' => $price]);

    expect(calculateCart($product, $this->consumer, $country)->shippingDisplayCents)->toBe($shipping);
})->with([
    'Germany is free from 50' => ['DE', 5000, 0],
    'Germany below' => ['DE', 4999, 390],
    'EU is free from 100' => ['AT', 10000, 0],
    'EU below' => ['AT', 9999, 990],
    'world has no free shipping' => ['CH', 100000, 1860],
]);

test('shipping is taxed at the rate of the largest line', function (int $reducedPrice, int $standardPrice, float $rate) {
    $reduced = Product::factory()->create(['price_cents' => $reducedPrice]);
    $standard = Product::factory()->standardRate()->create(['price_cents' => $standardPrice]);

    $totals = calculateCart([[$reduced, 1], [$standard, 1]], $this->consumer);

    expect($totals->shippingTaxRate)->toBe($rate)
        ->and($totals->shippingDisplayCents)->toBe(390);
})->with([
    'reduced line is larger' => [3000, 1000, 7.0],
    'standard line is larger' => [500, 3000, 19.0],
]);

test('the tax summary groups lines and shipping by rate, highest first', function () {
    $reduced = Product::factory()->create(['price_cents' => 1070]);
    $standard = Product::factory()->standardRate()->create(['price_cents' => 2380]);
    $otherReduced = Product::factory()->create(['price_cents' => 2140]);

    $totals = calculateCart([[$reduced, 1], [$standard, 1], [$otherReduced, 1]], $this->consumer);

    expect($totals->taxSummary)->toBe([
        ['rate' => 19.0, 'category' => 'S', 'net' => 2000, 'tax' => 380, 'gross' => 2380],
        ['rate' => 7.0, 'category' => 'S', 'net' => 3000, 'tax' => 210, 'gross' => 3210],
    ])->and($totals->shippingDisplayCents)->toBe(0)
        ->and(array_sum(array_column($totals->taxSummary, 'tax')))->toBe($totals->taxCents)
        ->and(array_sum(array_column($totals->taxSummary, 'gross')))->toBe($totals->totalCents);
});

test('paid shipping is part of the tax summary under the shipping rate', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    $totals = calculateCart($product, $this->consumer);

    expect($totals->taxSummary)->toBe([['rate' => 7.0, 'category' => 'S', 'net' => 1869 + 364, 'tax' => 131 + 26, 'gross' => 2390]]);
});

test('an empty cart has no shipping and no totals', function () {
    $totals = calculateCart([], $this->consumer);

    expect($totals->isEmpty())->toBeTrue()
        ->and($totals->shippingDisplayCents)->toBe(0)
        ->and($totals->totalCents)->toBe(0)
        ->and($totals->taxSummary)->toBe([]);
});

test('a blank country falls back to the home country', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    expect(calculateCart($product, $this->consumer, '')->treatment)->toBe(TaxTreatment::Domestic);
});

test('without a customer group prices include tax', function () {
    $product = Product::factory()->create(['price_cents' => 2000]);

    expect(calculateCart($product, null)->pricesIncludeTax)->toBeTrue();
});

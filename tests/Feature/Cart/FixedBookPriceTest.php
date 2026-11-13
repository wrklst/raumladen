<?php

use App\Cart\CartCalculator;
use App\Cart\CartLine;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Product;

test('a coupon does not reduce a fixed book price for consumers, but does elsewhere and for the trade', function (string $country, bool $consumer, ?string $error, int $discount) {
    config(['shop.tax.oss_registered' => false, 'shop.tax.small_business' => false]);

    $group = $consumer
        ? CustomerGroup::factory()->consumer()->create()
        : CustomerGroup::factory()->create(['prices_include_tax' => false, 'is_default' => false]);
    $lines = collect([new CartLine(Product::factory()->create(['price_cents' => 2000]), 1)]);
    $coupon = Coupon::factory()->create(['code' => 'TEN', 'type' => 'percent', 'value' => 10]);

    $totals = app(CartCalculator::class)->calculate($lines, $group, $country, false, $coupon);

    expect($totals->couponError)->toBe($error)
        ->and($totals->discountCents)->toBe($discount);
})->with([
    'consumer in Germany' => ['DE', true, 'fixed_price', 0],
    'consumer in Austria' => ['AT', true, 'fixed_price', 0],
    'consumer in France' => ['FR', true, null, 200],
    'consumer in Switzerland' => ['CH', true, null, 187],
    'trade customer in Germany' => ['DE', false, null, 187],
]);

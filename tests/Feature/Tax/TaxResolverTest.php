<?php

use App\Enums\OrderStatus;
use App\Enums\TaxTreatment;
use App\Models\Order;
use App\Tax\TaxResolver;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['shop.tax.small_business' => false, 'shop.tax.oss_registered' => false]);
    $this->travelTo('2026-06-15 12:00:00');
});

function distanceOrder(array $attributes = []): Order
{
    return Order::factory()->create([
        'tax_treatment' => TaxTreatment::DistanceHome,
        'subtotal_net_cents' => 600_000,
        ...$attributes,
    ]);
}

test('the small business regime wins over every destination', function (string $country, bool $vatIdValid) {
    config(['shop.tax.small_business' => true, 'shop.tax.oss_registered' => true]);

    expect(app(TaxResolver::class)->resolve($country, $vatIdValid))->toBe(TaxTreatment::SmallBusiness);
})->with([
    'domestic' => ['DE', false],
    'EU business' => ['AT', true],
    'third country' => ['CH', false],
]);

test('the destination is matched case-insensitively', function () {
    expect(app(TaxResolver::class)->resolve('de', false))->toBe(TaxTreatment::Domestic)
        ->and(app(TaxResolver::class)->resolve('at', true))->toBe(TaxTreatment::IntraCommunity);
});

test('digital goods to an EU business fall under reverse charge', function () {
    expect(app(TaxResolver::class)->resolve('AT', true, digital: true))->toBe(TaxTreatment::ReverseCharge)
        ->and(app(TaxResolver::class)->resolve('CH', true, digital: true))->toBe(TaxTreatment::Export)
        ->and(app(TaxResolver::class)->resolve('DE', true, digital: true))->toBe(TaxTreatment::Domestic);
});

test('an EU consumer stays at the home rate below the threshold', function () {
    expect(app(TaxResolver::class)->resolve('AT', false, thisSaleNetCents: 100_000))->toBe(TaxTreatment::DistanceHome);
});

test('sales of the previous year above the threshold switch to destination VAT', function () {
    Log::spy();
    distanceOrder(['subtotal_net_cents' => 1_000_001, 'created_at' => now()->subYear()]);

    expect(app(TaxResolver::class)->resolve('AT', false, thisSaleNetCents: 1))->toBe(TaxTreatment::Oss);
    Log::shouldHaveReceived('warning')->once();
});

test('sales of this year plus the current sale decide the threshold', function (int $thisSale, TaxTreatment $expected) {
    distanceOrder(['subtotal_net_cents' => 600_000]);
    distanceOrder(['subtotal_net_cents' => 399_999, 'tax_treatment' => TaxTreatment::Oss]);

    expect(app(TaxResolver::class)->resolve('AT', false, thisSaleNetCents: $thisSale))->toBe($expected);
})->with([
    'exactly at the limit is not crossed' => [1, TaxTreatment::DistanceHome],
    'one cent over' => [2, TaxTreatment::Oss],
]);

test('only EU distance sales of the relevant years that are not cancelled count', function () {
    $resolver = app(TaxResolver::class);

    distanceOrder(['subtotal_net_cents' => 500_000, 'created_at' => now()->subYears(2)]);
    distanceOrder(['subtotal_net_cents' => 500_000, 'status' => OrderStatus::Cancelled]);
    distanceOrder(['subtotal_net_cents' => 500_000, 'tax_treatment' => TaxTreatment::Domestic]);
    distanceOrder(['subtotal_net_cents' => 500_000, 'tax_treatment' => TaxTreatment::IntraCommunity]);
    distanceOrder(['subtotal_net_cents' => 123_456]);

    expect($resolver->distanceSalesNet(2026))->toBe(123_456)
        ->and($resolver->distanceSalesNet(2024))->toBe(500_000)
        ->and($resolver->crossesDistanceThreshold(0))->toBeFalse();
});

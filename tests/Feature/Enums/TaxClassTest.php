<?php

use App\Enums\TaxClass;

test('rateIn reads the configured rate for the class and country', function (TaxClass $class, string $country, float $rate) {
    expect($class->rateIn($country))->toBe($rate);
})->with([
    'DE standard' => [TaxClass::Standard, 'DE', 19.0],
    'DE reduced' => [TaxClass::Reduced, 'DE', 7.0],
    'FR reduced is fractional' => [TaxClass::Reduced, 'FR', 5.5],
    'FI standard is fractional' => [TaxClass::Standard, 'FI', 25.5],
    'lowercase country' => [TaxClass::Standard, 'at', 20.0],
    'zero class ignores the country' => [TaxClass::Zero, 'DE', 0.0],
    'unknown country has no rate' => [TaxClass::Standard, 'CH', 0.0],
]);

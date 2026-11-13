<?php

use App\Tax\TaxMath;

test('split derives net and tax from a gross amount', function (int $gross, float $rate, int $net, int $tax) {
    expect(TaxMath::split($gross, $rate, true))->toBe(['net' => $net, 'tax' => $tax, 'gross' => $gross]);
})->with([
    'reduced 7 %' => [1070, 7.0, 1000, 70],
    'standard 19 %' => [2390, 19.0, 2008, 382],
    'fractional rate' => [1055, 5.5, 1000, 55],
    'zero rate' => [500, 0.0, 500, 0],
]);

test('split adds tax on top of a net amount', function (int $net, float $rate, int $tax) {
    expect(TaxMath::split($net, $rate, false))->toBe(['net' => $net, 'tax' => $tax, 'gross' => $net + $tax]);
})->with([
    'standard 19 %' => [1000, 19.0, 190],
    'rounds half up' => [999, 7.0, 70],
    'fractional rate' => [1000, 5.5, 55],
    'zero rate' => [1000, 0.0, 0],
]);

test('netOf returns the unrounded net hidden in a gross price', function () {
    expect(TaxMath::netOf(1070, 7.0))->toEqualWithDelta(1000.0, 0.0001)
        ->and(TaxMath::netOf(2390, 19.0))->toEqualWithDelta(2008.4034, 0.001);
});

test('distribute hands out the remainder to the largest fractions', function (int $total, array $weights, array $expected) {
    $parts = TaxMath::distribute($total, $weights);

    expect($parts)->toBe($expected)->and(array_sum($parts))->toBe($total);
})->with([
    'equal weights' => [100, [1, 1, 1], [34, 33, 33]],
    'half fractions go to the first' => [10, [3, 1], [8, 2]],
    'proportional' => [300, [100, 200], [100, 200]],
    'keys are preserved' => [50, ['a' => 1, 'b' => 3], ['a' => 13, 'b' => 37]],
    'single weight takes everything' => [7, [5], [7]],
]);

test('distribute of nothing is zero', function (int $total, array $weights, array $expected) {
    expect(TaxMath::distribute($total, $weights))->toBe($expected);
})->with([
    'zero total' => [0, [1, 2], [0, 0]],
    'empty weights' => [100, [], []],
    'all weights zero' => [100, [0, 0], [0, 0]],
]);

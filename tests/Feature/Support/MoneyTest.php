<?php

use App\Support\Money;

test('format renders cents in the locale of the visitor', function (string $locale, int $cents, string $expected) {
    expect(Money::format($cents, 'EUR', $locale))->toBe($expected);
})->with([
    'german' => ['de', 1990, "19,90\u{A0}€"],
    'english' => ['en', 1990, '€19.90'],
    'thousands' => ['de', 123456, "1.234,56\u{A0}€"],
    'zero' => ['en', 0, '€0.00'],
]);

test('format falls back to the app locale and shop currency', function () {
    app()->setLocale('en');

    expect(Money::format(250))->toBe('€2.50');
});

test('decimal is a dotted string for feeds', function (int $cents, string $expected) {
    expect(Money::decimal($cents))->toBe($expected);
})->with([
    [1290, '12.90'],
    [5, '0.05'],
    [0, '0.00'],
    [123456, '1234.56'],
]);

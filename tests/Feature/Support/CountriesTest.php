<?php

use App\Support\Countries;

test('isEu knows member states regardless of case', function (string $country, bool $expected) {
    expect(Countries::isEu($country))->toBe($expected);
})->with([
    'Germany' => ['DE', true],
    'lowercase' => ['at', true],
    'Greece' => ['GR', true],
    'Greek VAT prefix is not a country code' => ['EL', false],
    'United Kingdom' => ['GB', false],
    'Switzerland' => ['CH', false],
]);

test('the Greek VAT prefix maps to GR', function (string $prefix, string $country) {
    expect(Countries::fromVatPrefix($prefix))->toBe($country);
})->with([
    'EL' => ['EL', 'GR'],
    'lowercase el' => ['el', 'GR'],
    'other prefixes are upper-cased' => ['at', 'AT'],
]);

test('names are localized', function () {
    expect(Countries::name('DE', 'de'))->toBe('Deutschland')
        ->and(Countries::name('AT', 'en'))->toBe('Austria');
});

test('options put the home country first, then the EU alphabetically, then the rest', function () {
    $options = Countries::options('en');
    $keys = array_keys($options);

    expect($keys[0])->toBe('DE')
        ->and(array_slice($keys, 1, 26))->toEqualCanonicalizing(array_values(array_diff(Countries::EU, ['DE'])))
        ->and(array_slice($options, 1, 26))->toBe(collect(array_slice($options, 1, 26))->sort()->all())
        ->and(array_search('CH', $keys, true))->toBeGreaterThan(26)
        ->and($options['AT'])->toBe('Austria');
});

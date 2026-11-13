<?php

use App\Enums\TaxTreatment;

test('each treatment has its EN 16931 category', function (TaxTreatment $treatment, string $category, bool $taxed) {
    expect($treatment->category())->toBe($category)->and($treatment->isTaxed())->toBe($taxed);
})->with([
    [TaxTreatment::Domestic, 'S', true],
    [TaxTreatment::DistanceHome, 'S', true],
    [TaxTreatment::Oss, 'S', true],
    [TaxTreatment::IntraCommunity, 'K', false],
    [TaxTreatment::Export, 'G', false],
    [TaxTreatment::ReverseCharge, 'AE', false],
    [TaxTreatment::SmallBusiness, 'E', false],
]);

test('rateCountry names the country whose rates apply', function (TaxTreatment $treatment, ?string $expected) {
    expect($treatment->rateCountry('AT'))->toBe($expected);
})->with([
    'domestic uses the home rate' => [TaxTreatment::Domestic, 'DE'],
    'distance sale below threshold uses the home rate' => [TaxTreatment::DistanceHome, 'DE'],
    'OSS uses the destination rate' => [TaxTreatment::Oss, 'AT'],
    'intra-community charges nothing' => [TaxTreatment::IntraCommunity, null],
    'reverse charge charges nothing' => [TaxTreatment::ReverseCharge, null],
    'export charges nothing' => [TaxTreatment::Export, null],
    'small business charges nothing' => [TaxTreatment::SmallBusiness, null],
]);

test('exempt treatments print a legal note in the order locale', function (TaxTreatment $treatment, string $de, string $en) {
    expect($treatment->note('de'))->toContain($de)
        ->and($treatment->note('en'))->toContain($en);
})->with([
    [TaxTreatment::IntraCommunity, '§ 4 Nr. 1b', 'intra-Community'],
    [TaxTreatment::ReverseCharge, '§ 13b UStG', 'Reverse charge'],
    [TaxTreatment::Export, '§ 4 Nr. 1a', 'export'],
    [TaxTreatment::SmallBusiness, '§ 19 UStG', '§ 19 UStG'],
]);

test('taxed treatments need no note', function (TaxTreatment $treatment) {
    expect($treatment->note('de'))->toBeNull();
})->with([TaxTreatment::Domestic, TaxTreatment::DistanceHome, TaxTreatment::Oss]);

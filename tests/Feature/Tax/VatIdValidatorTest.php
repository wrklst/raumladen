<?php

use App\Tax\VatIdResult;
use App\Tax\VatIdValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function validateVatId(string $vatId): VatIdResult
{
    return app(VatIdValidator::class)->validate($vatId);
}

test('a confirmed VAT ID is valid and normalized', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => true])]);

    $result = validateVatId('at u 1234-5678');

    expect($result->valid)->toBeTrue()
        ->and($result->serviceAvailable)->toBeTrue()
        ->and($result->vatId)->toBe('ATU12345678')
        ->and($result->country)->toBe('AT');

    Http::assertSent(fn (Request $request) => $request->url() === VatIdValidator::ENDPOINT
        && $request['countryCode'] === 'AT'
        && $request['vatNumber'] === 'U12345678');
});

test('a VAT ID that VIES rejects is invalid but the service was available', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => false])]);

    $result = validateVatId('ATU00000000');

    expect($result->valid)->toBeFalse()->and($result->serviceAvailable)->toBeTrue();
});

test('a validation error from VIES is invalid, not unavailable', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['errorWrappers' => [['error' => 'INVALID_INPUT']]], 400)]);

    $result = validateVatId('ATU00000000');

    expect($result->valid)->toBeFalse()->and($result->serviceAvailable)->toBeTrue();
});

test('malformed input is invalid without calling VIES', function (string $input) {
    Http::fake();

    $result = validateVatId($input);

    expect($result->valid)->toBeFalse()->and($result->serviceAvailable)->toBeTrue();
    Http::assertNothingSent();
})->with([
    'empty' => [''],
    'digits only' => ['12345678'],
    'single letter prefix' => ['A1234'],
    'too short' => ['AT1'],
    'too long' => ['ATU1234567890123'],
]);

test('a non-EU prefix is invalid without calling VIES', function (string $vatId, string $country) {
    Http::fake();

    $result = validateVatId($vatId);

    expect($result->valid)->toBeFalse()->and($result->country)->toBe($country);
    Http::assertNothingSent();
})->with([
    'Switzerland' => ['CHE123456789', 'CH'],
    'United Kingdom' => ['GB123456789', 'GB'],
]);

test('a Greek EL prefix is sent to VIES as EL and reported as GR', function () {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => true])]);

    $result = validateVatId('EL123456789');

    expect($result->valid)->toBeTrue()->and($result->country)->toBe('GR');
    Http::assertSent(fn (Request $request) => $request['countryCode'] === 'EL' && $request['vatNumber'] === '123456789');
});

test('an unavailable VIES is never treated as valid', function (callable $response) {
    Http::fake([VatIdValidator::ENDPOINT => $response]);

    $result = validateVatId('ATU12345678');

    expect($result->valid)->toBeFalse()->and($result->serviceAvailable)->toBeFalse();
})->with([
    'HTTP 500' => [fn () => Http::response('', 500)],
    'HTTP 503' => [fn () => Http::response('', 503)],
    'HTTP 429' => [fn () => Http::response('', 429)],
    'member state unavailable' => [fn () => Http::response(['valid' => false, 'errorWrappers' => [['error' => 'MS_UNAVAILABLE']]])],
    'member state busy' => [fn () => Http::response(['errorWrappers' => [['error' => 'MS_MAX_CONCURRENT_REQ']]], 200)],
    'timeout' => [fn () => Http::response(['errorWrappers' => [['error' => 'TIMEOUT']]], 200)],
    'connection failure' => [fn () => throw new ConnectionException('cURL error 28')],
]);

test('an unavailable result is not cached', function () {
    Http::fakeSequence(VatIdValidator::ENDPOINT)
        ->push('', 503)
        ->push(['valid' => true]);

    expect(validateVatId('ATU12345678')->serviceAvailable)->toBeFalse()
        ->and(validateVatId('ATU12345678')->valid)->toBeTrue();
});

test('a second lookup of the same VAT ID is served from the cache', function (bool $valid) {
    Http::fake([VatIdValidator::ENDPOINT => Http::response(['valid' => $valid])]);

    $first = validateVatId('ATU12345678');
    $second = validateVatId('at u12345678');

    expect($first->valid)->toBe($valid)->and($second->valid)->toBe($valid);
    Http::assertSentCount(1);
})->with([
    'valid' => [true],
    'invalid' => [false],
]);

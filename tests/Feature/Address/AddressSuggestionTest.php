<?php

use App\Address\AddressSuggester;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * No test calls photon.komoot.io: that would be the bulk querying its fair-use
 * terms forbid. The response shapes are the ones recorded for the Kulmine shop.
 */
function photonHouse(): array
{
    return ['features' => [
        ['properties' => ['type' => 'house', 'housenumber' => '157', 'name' => 'LöwenCenter', 'street' => 'Hauptstraße', 'city' => 'Bergisch Gladbach', 'postcode' => '51465', 'countrycode' => 'DE']],
        ['properties' => ['type' => 'house', 'housenumber' => '157', 'name' => 'Polizeiwache', 'street' => 'Hauptstraße', 'city' => 'Bergisch Gladbach', 'postcode' => '51465', 'countrycode' => 'DE']],
    ]];
}

function photonStreet(): array
{
    return ['features' => [
        ['properties' => ['type' => 'street', 'name' => 'Bahnhofstrasse', 'city' => 'Zürich', 'postcode' => '8001', 'countrycode' => 'CH']],
        ['properties' => ['type' => 'street', 'name' => 'Bahnhofstrasse', 'city' => 'Zürich', 'postcode' => '8001', 'countrycode' => 'CH']],
        ['properties' => ['type' => 'street', 'name' => 'Beethovenstraat', 'city' => 'Vilvoorde', 'postcode' => '1800', 'countrycode' => 'BE']],
    ]];
}

function suggestionsUrl(array $query): string
{
    return route('shop.address-suggestions', ['locale' => 'de', ...$query]);
}

test('a house hit becomes form fields; the POI name is dropped and duplicates merge', function () {
    Http::fake([AddressSuggester::ENDPOINT.'*' => Http::response(photonHouse())]);

    $this->getJson(suggestionsUrl(['q' => 'Bergisch Gladbach Hauptstr', 'country' => 'DE']))
        ->assertOk()
        ->assertExactJson([
            'suggestions' => [[
                'address1' => 'Hauptstraße 157',
                'zip' => '51465',
                'city' => 'Bergisch Gladbach',
                'country' => 'DE',
                'label' => 'Hauptstraße 157, 51465 Bergisch Gladbach',
            ]],
            'attribution' => AddressSuggester::ATTRIBUTION,
        ]);

    Http::assertSent(fn (Request $request) => $request['q'] === 'Bergisch Gladbach Hauptstr'
        && $request['countrycode'] === 'DE'
        && $request['layer'] === 'house'
        && $request['lang'] === 'de'
        && str_contains($request->header('User-Agent')[0], 'address suggestions'));
});

test('without a house hit streets are asked; other countries are filtered out', function () {
    Http::fakeSequence(AddressSuggester::ENDPOINT.'*')->push(['features' => []])->push(photonStreet());

    $suggestions = app(AddressSuggester::class)->suggest('Bahnhofstrasse Zürich', 'ch');

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0])->toMatchArray(['address1' => 'Bahnhofstrasse', 'zip' => '8001', 'city' => 'Zürich', 'country' => 'CH']);
    Http::assertSentCount(2);
});

test('answers are cached, the empty ones too, so a repeated prefix costs nothing', function () {
    Http::fake([AddressSuggester::ENDPOINT.'*' => Http::response(['features' => []])]);
    $suggester = app(AddressSuggester::class);

    expect($suggester->suggest('Haupts', 'DE'))->toBe([])
        ->and($suggester->suggest('  haupts ', 'DE'))->toBe([]);

    // One house and one street request for the first call, none for the second.
    Http::assertSentCount(2);
});

test('too short input and an unusable country never reach the service', function () {
    Http::fake([AddressSuggester::ENDPOINT.'*' => Http::response(photonHouse())]);
    $suggester = app(AddressSuggester::class);

    expect($suggester->suggest('Ha', 'DE'))->toBe([]);
    Http::assertNothingSent();

    $suggester->suggest(str_repeat('Hauptstraße ', 40), 'DE,AT');

    Http::assertSent(fn (Request $request) => mb_strlen($request['q']) === 120 && ! isset($request['countrycode']));
});

test('a failure returns no suggestions and pauses requests for ten minutes', function () {
    Http::fake([AddressSuggester::ENDPOINT.'*' => Http::response('', 503)]);
    $suggester = app(AddressSuggester::class);

    expect($suggester->suggest('Hauptstraße 1', 'DE'))->toBe([])
        ->and($suggester->suggest('Bahnhofstraße 2', 'DE'))->toBe([]);
    Http::assertSentCount(1);

    $this->travel(11)->minutes();
    $suggester->suggest('Bahnhofstraße 2', 'DE');

    Http::assertSentCount(2);
    // A failure is not an answer: nothing was cached for the query itself.
    expect(Cache::has('address:'.md5('hauptstraße 1|DE|de')))->toBeFalse();
});

test('the endpoint is throttled per client', function () {
    Http::fake([AddressSuggester::ENDPOINT.'*' => Http::response(['features' => []])]);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson(suggestionsUrl(['q' => 'ab']))->assertOk();
    }

    $this->getJson(suggestionsUrl(['q' => 'ab']))->assertTooManyRequests();
});

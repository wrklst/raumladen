<?php

use App\Models\CustomerGroup;
use App\Models\User;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
});

test('an unsupported locale prefix is a 404', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/fr', '/fr/cart', '/xx/products/anything']);

test('visiting a storefront page stores and activates the locale', function (string $locale) {
    $this->get(route('shop.cart.index', ['locale' => $locale]))->assertOk();

    expect(session('locale'))->toBe($locale)
        ->and(app()->getLocale())->toBe($locale);
})->with(['de', 'en']);

test('the root redirects by session locale, then Accept-Language, then the default', function (?string $session, ?string $header, string $expected) {
    $response = $this
        ->withSession($session === null ? [] : ['locale' => $session])
        ->withHeaders($header === null ? [] : ['Accept-Language' => $header])
        ->get('/');

    $response->assertRedirect(route('shop.home', ['locale' => $expected]));
})->with([
    'session wins over the browser' => ['de', 'en-US,en;q=0.9', 'de'],
    'session english' => ['en', 'de', 'en'],
    'browser english' => [null, 'en-US,en;q=0.9', 'en'],
    'browser german' => [null, 'de-DE,de;q=0.9,en;q=0.8', 'de'],
    'browser prefers an unsupported language, then english' => [null, 'fr-FR,fr;q=0.9,en;q=0.5', 'en'],
    'browser asks only for unsupported languages' => [null, 'fr-FR,fr;q=0.9', 'de'],
    'stale unsupported session locale' => ['fr', 'en', 'de'],
]);

test('the root without any language preference falls back to the shop default locale', function () {
    // The test client sends Accept-Language: en-us by default; a client without the header sends none.
    $this->withHeaders(['Accept-Language' => ''])->get('/')->assertRedirect(route('shop.home', ['locale' => 'de']));
});

test('the root redirect is a plain redirect that keeps working for a returning visitor', function () {
    $this->get('/en/cart');

    $this->get('/')->assertRedirect(route('shop.home', ['locale' => 'en']));
});

test('guests are sent to the matching login page', function (string $path, string $route) {
    $this->get($path)->assertRedirect(route($route));
})->with([
    'admin' => ['/admin', 'login'],
    'settings' => ['/settings/profile', 'login'],
    'dashboard' => ['/dashboard', 'login'],
]);

test('a customer without a stored locale lands on the default locale', function () {
    $customer = User::factory()->create(['locale' => null]);

    $this->actingAs($customer)->get('/dashboard')->assertRedirect(route('shop.account.orders.index', ['locale' => 'de']));
});

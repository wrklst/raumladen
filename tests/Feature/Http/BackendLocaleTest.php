<?php

use App\Models\CustomerGroup;
use App\Models\User;

test('the locale of the login page is resolved from session, browser and default', function (array $session, ?string $accept, string $expected) {
    $headers = $accept === null ? ['Accept-Language' => ''] : ['Accept-Language' => $accept];

    $this->withSession($session)->withHeaders($headers)->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('locale', $expected));
})->with([
    'session wins over browser' => [['locale' => 'de'], 'en-US,en;q=0.9', 'de'],
    'browser language' => [[], 'de-DE,de;q=0.9,en;q=0.5', 'de'],
    'browser english' => [[], 'en-GB', 'en'],
    'unsupported session value falls through' => [['locale' => 'fr'], 'de', 'de'],
    'unsupported browser falls to default' => [[], 'fr-FR,fr;q=0.9', 'de'],
    'no information gives the default' => [[], null, 'de'],
]);

test('the locale of the signed in user wins over session and browser', function () {
    $this->actingAs(User::factory()->admin()->create(['locale' => 'de']))
        ->withSession(['locale' => 'en'])
        ->withHeaders(['Accept-Language' => 'en-US'])
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('locale', 'de')->where('translations.Orders', 'Bestellungen'));
});

test('an unsupported user locale falls through to the session', function () {
    $this->actingAs(User::factory()->admin()->create(['locale' => 'fr']))
        ->withSession(['locale' => 'de'])
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('locale', 'de'));
});

test('an admin switches the language and it is stored on the user', function () {
    $admin = User::factory()->admin()->create(['locale' => null]);

    $this->actingAs($admin)->from(route('admin.dashboard'))
        ->post(route('locale.update'), ['locale' => 'de'])
        ->assertRedirect(route('admin.dashboard'));

    expect($admin->fresh()->locale)->toBe('de');

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('locale', 'de')->where('translations.Orders', 'Bestellungen'));

    $this->post(route('locale.update'), ['locale' => 'en']);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('locale', 'en')->where('translations', []));
});

test('a guest switches the language in the session', function () {
    $this->post(route('locale.update'), ['locale' => 'en'])->assertSessionHas('locale', 'en');

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

test('an unsupported language is rejected', function () {
    $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
});

test('admin validation messages are german for a german admin', function () {
    CustomerGroup::factory()->consumer()->create();

    $this->actingAs(User::factory()->admin()->create(['locale' => 'de']))
        ->post(route('admin.products.store'), ['type' => 'book', 'title' => ['de' => ''], 'tax_class' => 'reduced', 'price' => 'abc', 'stock' => 1])
        ->assertSessionHasErrors([
            'title.de' => 'Titel (Deutsch) ist ein Pflichtfeld.',
            'price' => 'Preis muss eine Zahl sein.',
        ]);
});

test('storefront checkout validation messages are german', function () {
    CustomerGroup::factory()->consumer()->create();

    $this->post(route('shop.checkout.store', ['locale' => 'de']), ['email' => 'kein-email'])
        ->assertSessionHasErrors(['email' => 'E-Mail-Adresse muss eine gültige E-Mail-Adresse sein.']);
});

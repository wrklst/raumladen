<?php

test('the root redirects to the preferred storefront locale', function () {
    $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])
        ->get('/')
        ->assertRedirect(route('shop.home', ['locale' => 'en']));
});

test('the root falls back to the default locale', function () {
    $this->withHeaders(['Accept-Language' => 'fr'])
        ->get('/')
        ->assertRedirect(route('shop.home', ['locale' => 'de']));
});

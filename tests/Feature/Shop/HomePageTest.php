<?php

use App\Models\CustomerGroup;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
});

test('the storefront home lists active products with structured data', function () {
    $product = Product::factory()->create(['title' => ['de' => 'Testbuch', 'en' => 'Test Book']]);
    Product::factory()->inactive()->create();

    $response = $this->get(route('shop.home', ['locale' => 'de']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('shop/home')
        ->has('products.data', 1)
        ->where('products.data.0.title', 'Testbuch')
        ->where('seo.json_ld.@graph.2.@type', 'ItemList')
        ->where('seo.json_ld.@graph.2.itemListElement.0.url', $product->urlFor('de'))
    );
});

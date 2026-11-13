<?php

use App\Models\CustomerGroup;
use App\Models\Page;
use App\Models\Product;
use App\Theme\Theme;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
});

test('a product is reached by the slug of the request locale only', function () {
    // Regression: the locale used to be set after route model binding, so
    // every German product page was a 404 while APP_LOCALE was "en".
    config(['app.locale' => 'en']);

    $product = Product::factory()->create([
        'title' => ['de' => 'Raum', 'en' => 'Space'],
        'slug' => ['de' => 'raum', 'en' => 'space'],
    ]);

    $this->get('/de/products/raum')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('shop/product')
        ->where('product.title', 'Raum')
        ->where('delivery_days', [2, 4])
        ->where('seo.title', 'Raum'));
    $this->get('/en/products/space')->assertOk()->assertInertia(fn (Assert $page) => $page->where('product.title', 'Space'));
    $this->get('/de/products/space')->assertNotFound();
    $this->get('/en/products/raum')->assertNotFound();
});

test('inactive and unpublished products are not reachable', function () {
    $inactive = Product::factory()->inactive()->create();
    $future = Product::factory()->create(['published_at' => now()->addDay()]);

    $this->get($inactive->urlFor('de'))->assertNotFound();
    $this->get($future->urlFor('de'))->assertNotFound();
});

test('the storefront html carries the class of its theme', function () {
    $html = $this->get('/de')->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="de" class="'.Theme::current()->cssClass().'">')
        ->toContain('<meta name="color-scheme" content="light dark">');
});

test('the colour scheme cookie decides the server-rendered class', function () {
    $html = $this->withUnencryptedCookie('appearance', 'dark')->get('/de')->getContent();

    expect($html)->toContain('<html lang="de" class="dark '.Theme::current()->cssClass().'">');
});

test('a content page renders its markdown without raw html', function () {
    Page::factory()->create([
        'slug' => 'imprint',
        'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
        'body' => ['de' => "## Angaben\n\n<script>alert(1)</script>Text", 'en' => 'Text'],
    ]);

    $this->get('/de/pages/imprint')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('shop/page')
        ->where('page.title', 'Impressum')
        ->where('page.html', fn (string $html) => str_contains($html, '<h2>Angaben</h2>') && ! str_contains($html, '<script')));

    Page::factory()->create(['slug' => 'draft', 'is_published' => false]);

    $this->get('/de/pages/draft')->assertNotFound();
});

test('the seeder provides the legal pages, the accessibility statement and a demo catalogue', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Page::query()->where('is_published', true)->pluck('slug')->all())
        ->toEqualCanonicalizing(['imprint', 'terms', 'withdrawal', 'privacy', 'shipping', 'accessibility']);

    $this->get('/en/products/the-art-of-slow-reading')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('product.title', 'The Art of Slow Reading')
        ->where('product.authors', 'Lena Hartmann')
        ->where('product.images', []));

    // Seeding twice must not duplicate anything.
    $this->seed(DatabaseSeeder::class);

    expect(Page::query()->count())->toBe(6)
        ->and(Product::query()->count())->toBe(3);
});

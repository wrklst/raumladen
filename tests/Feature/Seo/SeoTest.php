<?php

use App\Models\CustomerGroup;
use App\Models\Page;
use App\Models\Product;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    config(['app.name' => 'Space Issues']);
});

/**
 * The JSON-LD graph from the page's <head>.
 *
 * @return array<int, array<string, mixed>>
 */
function jsonLdGraph(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);

    return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

function book(array $attributes = []): Product
{
    return Product::factory()->create([
        'title' => ['de' => 'Raum und Zeit', 'en' => 'Space and Time'],
        'slug' => ['de' => 'raum-und-zeit', 'en' => 'space-and-time'],
        'description' => ['de' => 'Ein Buch über Raum.', 'en' => 'A book about space.'],
        'price_cents' => 2400,
        'specs' => ['isbn' => '978-3-69354-012-1', 'authors' => 'Blanca Borrell'],
        ...$attributes,
    ]);
}

test('robots.txt comes from the app, keeps private areas out and names only the sitemap', function () {
    // A static public/robots.txt would be served by the web server instead of this route.
    expect(file_exists(public_path('robots.txt')))->toBeFalse();

    $robots = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();

    expect($robots)
        ->toContain("User-agent: *\nAllow: /\n")
        ->toContain('Disallow: /admin', 'Disallow: /*/cart', 'Disallow: /*/checkout', 'Disallow: /*/account', 'Disallow: /*/address-suggestions')
        ->toContain('Sitemap: '.route('seo.sitemap.index'))
        ->and(substr_count($robots, 'Sitemap:'))->toBe(1);
});

test('llms.txt describes the series, its books, shipping and withdrawal for AI crawlers', function () {
    $product = book();
    Product::factory()->inactive()->create(['title' => ['de' => 'Unsichtbar', 'en' => 'Hidden']]);
    Page::factory()->create(['slug' => 'imprint', 'title' => ['de' => 'Impressum', 'en' => 'Imprint']]);

    $text = $this->get('/llms.txt')->assertOk()->getContent();

    expect($text)
        ->toStartWith("# Space Issues\n\n> Space Issues ist eine Buchreihe")
        ->toContain('Ludwig Engel, Maria Mazzanti, Julian Schubert')
        ->toContain('- [Raum und Zeit]('.$product->urlFor('de').'): Ein Buch über Raum.')
        ->toContain('ISBN 978-3-69354-012-1', "24,00\u{a0}€")
        ->toContain("- DE: 3,90\u{a0}€, versandkostenfrei ab 50,00\u{a0}€, 2–4 Werktage")
        ->toContain('Widerrufsrecht: 14 Tage')
        ->toContain('- [Impressum]('.route('shop.pages.show', ['locale' => 'de', 'page' => 'imprint']).')')
        ->not->toContain('Unsichtbar');
});

test('the sitemap lists every locale with hreflang alternates', function () {
    $product = book();
    Product::factory()->inactive()->create();
    Page::factory()->create(['slug' => 'imprint']);

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee(route('seo.sitemap.locale', 'de'))
        ->assertSee(route('seo.sitemap.locale', 'en'));

    $xml = $this->get('/sitemap-de.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)
        ->toContain('<loc>'.route('shop.home', ['locale' => 'de']).'</loc>')
        ->toContain('<loc>'.$product->urlFor('de').'</loc>')
        ->toContain('hreflang="en" href="'.$product->urlFor('en').'"')
        ->toContain(route('shop.pages.show', ['locale' => 'de', 'page' => 'imprint']))
        ->and(substr_count($xml, '<url>'))->toBe(3);

    $this->get('/sitemap-fr.xml')->assertNotFound();
});

test('the merchant feed carries price, availability and gtin', function () {
    $product = book(['stock' => 0, 'track_stock' => true]);

    $xml = $this->get('/feeds/google-en.xml')->assertOk()->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)
        ->toContain('Space and Time', $product->urlFor('en'))
        ->toContain('24.00 EUR', 'out_of_stock', '9783693540121');
});

test('a product page head has title, canonical, hreflang, Open Graph and Book structured data', function () {
    $product = book();

    $html = $this->get($product->urlFor('en'))->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="en"')
        ->toContain('<title>Space and Time – Space Issues</title>')
        ->toContain('<meta name="description" content="A book about space.">')
        ->toContain('<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">')
        ->toContain('<link rel="canonical" href="'.$product->urlFor('en').'">')
        ->toContain('<link rel="alternate" hreflang="de" href="'.$product->urlFor('de').'">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="'.$product->urlFor('de').'">')
        ->toContain('<meta property="og:type" content="product">')
        ->toContain('<meta property="og:title" content="Space and Time">')
        ->toContain('<meta property="og:site_name" content="Space Issues">')
        ->toContain('<meta property="og:locale" content="en_US">')
        ->toContain('<meta property="og:locale:alternate" content="de_DE">');

    $graph = collect(jsonLdGraph($html));
    $bookNode = $graph->first(fn (array $node) => $node['@type'] === ['Product', 'Book']);

    expect($bookNode)
        ->toMatchArray(['name' => 'Space and Time', 'isbn' => '9783693540121', 'gtin13' => '9783693540121'])
        ->and($bookNode['author'])->toBe([['@type' => 'Person', 'name' => 'Blanca Borrell']])
        ->and($bookNode['offers'])->toMatchArray([
            'price' => '24.00',
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
            'url' => $product->urlFor('en'),
        ])
        ->and($bookNode['offers']['shippingDetails']['shippingRate'])->toMatchArray(['value' => '3.90', 'currency' => 'EUR'])
        ->and($bookNode['offers']['shippingDetails']['deliveryTime']['transitTime'])->toMatchArray(['minValue' => 2, 'maxValue' => 4])
        ->and($graph->firstWhere('@type', 'BreadcrumbList')['itemListElement'])->toHaveCount(2)
        ->and($graph->firstWhere('@type', 'WebSite'))->toMatchArray(['name' => 'Space Issues', 'inLanguage' => 'en'])
        ->and($graph->firstWhere('@type', 'Organization'))
        ->toMatchArray(['sameAs' => ['http://spacedepartment.net']])
        ->and($graph->firstWhere('@type', 'Organization')['hasMerchantReturnPolicy'])
        ->toMatchArray(['merchantReturnDays' => 14, 'returnFees' => 'https://schema.org/ReturnFeesCustomerResponsibility']);
});

test('a product without a slug in a locale has no alternate there', function () {
    $product = book(['slug' => ['de' => 'nur-deutsch'], 'title' => ['de' => 'Nur deutsch']]);

    $html = $this->get($product->urlFor('de'))->assertOk()->getContent();

    expect($html)->toContain('hreflang="de"')->not->toContain('hreflang="en"');
});

test('later catalogue pages are canonical to themselves', function () {
    Product::factory()->count(25)->create();

    expect($this->get('/de')->getContent())->toContain('<link rel="canonical" href="'.route('shop.home', ['locale' => 'de']).'">')
        ->and($this->get('/de?page=2')->getContent())->toContain('<link rel="canonical" href="'.route('shop.home', ['locale' => 'de', 'page' => 2]).'">');
});

test('cart, checkout and login are kept out of the index', function (string $route) {
    $product = book();

    $this->withSession(['cart.items' => [$product->id => 1]])
        ->get(route($route, ['locale' => 'de']))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
})->with(['shop.cart.index', 'shop.checkout.show', 'shop.account.login']);

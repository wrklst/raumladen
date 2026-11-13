<?php

use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();

    $this->morning = Product::factory()->create([
        'title' => ['de' => 'The Morning of Space', 'en' => 'The Morning of Space'],
        'description' => ['de' => 'Olivenlandschaften von Jaén.', 'en' => 'Olive landscapes of Jaén.'],
        'specs' => ['isbn' => '978-3-00-000001-1', 'authors' => 'Anna Beispiel'],
        'sku' => 'SI-1',
    ]);
    $this->zurich = Product::factory()->create([
        'title' => ['de' => 'Zürich bauen', 'en' => 'Building Zurich'],
        'description' => ['de' => 'Über Raum und Stadt.', 'en' => 'On space and the city.'],
        'specs' => ['isbn' => '978-3-00-000002-8', 'authors' => 'Jonas Weber, Anna Beispiel'],
        'sku' => 'SI-2',
    ]);
    Product::factory()->inactive()->create(['title' => ['de' => 'Space unsichtbar', 'en' => 'Space hidden']]);
});

function searchTitles(string $query, string $locale = 'de'): array
{
    $titles = [];

    test()->get(route('shop.home', ['locale' => $locale, 'q' => $query]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$titles, $query) {
            $titles = array_column($page->toArray()['props']['products']['data'], 'title');
            $page->where('filters.q', trim($query));
        });

    return $titles;
}

test('the search finds by title, author, isbn and description, ignoring case, accents and hyphens', function (string $query, array $expected) {
    expect(searchTitles($query))->toBe($expected);
})->with([
    'title word' => ['morning', ['The Morning of Space']],
    'case and spacing' => ['  MORNING   space ', ['The Morning of Space']],
    'umlaut typed plainly' => ['zurich', ['Zürich bauen']],
    'title in the other language' => ['building', ['Zürich bauen']],
    'author' => ['weber', ['Zürich bauen']],
    'isbn with hyphens' => ['978-3-00-000001-1', ['The Morning of Space']],
    'isbn without hyphens' => ['9783000000011', ['The Morning of Space']],
    'article number' => ['si-2', ['Zürich bauen']],
    'accent in the description' => ['jaen', ['The Morning of Space']],
    'every word has to match' => ['morning zurich', []],
    'inactive books stay hidden' => ['unsichtbar', []],
]);

test('a title hit ranks above a description hit', function () {
    // "space" is in one title and only in the other's English description.
    expect(searchTitles('space', 'en'))->toBe(['The Morning of Space', 'Building Zurich']);
});

test('search results are not indexed and empty input lists everything', function () {
    $this->get(route('shop.home', ['locale' => 'de', 'q' => 'morning']))
        ->assertInertia(fn (Assert $page) => $page->where('seo.index', false)->where('seo.title', 'Suche: morning'));

    expect(searchTitles(''))->toHaveCount(2);
});

test('an author link lists all issues by that author', function () {
    $this->get(route('shop.home', ['locale' => 'de', 'author' => 'Anna Beispiel']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('filters.author', 'Anna Beispiel')
            ->where('seo.title', 'Anna Beispiel')
            ->where('seo.index', true)
            ->where('seo.canonical', route('shop.home', ['locale' => 'de', 'author' => 'Anna Beispiel'])));

    $this->get(route('shop.home', ['locale' => 'de', 'author' => 'jonas weber']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.title', 'Zürich bauen'));

    // A partial name is a search, not an author.
    $this->get(route('shop.home', ['locale' => 'de', 'author' => 'Anna']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('the issue page lists its authors one by one for the links', function () {
    $this->get($this->zurich->urlFor('de'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.author_list', ['Jonas Weber', 'Anna Beispiel'])
            ->where('manufacturer', fn (string $line) => str_contains($line, config('shop.seller.name'))));
});

test('the search runs on a cached index and loads only the products it shows', function () {
    searchTitles('morning');

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    expect(searchTitles('zurich'))->toBe(['Zürich bauen']);

    // No scan of the catalogue any more: products are read once, by id, for the hits.
    $productQueries = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'from "products"')));

    expect($productQueries)->toHaveCount(1)
        ->and($productQueries[0])->toContain('"id" in (');
});

test('the index follows the catalogue: new, changed, deleted and newly published issues', function () {
    searchTitles('morning');

    $new = Product::factory()->create(['title' => ['de' => 'Neuland', 'en' => 'New Ground'], 'published_at' => now()->addDay()]);

    // Scheduled for tomorrow: indexed, but not visible yet.
    expect(searchTitles('neuland'))->toBe([]);

    $this->travel(2)->days();

    expect(searchTitles('neuland'))->toBe(['Neuland']);

    $new->update(['title' => ['de' => 'Altland', 'en' => 'Old Ground']]);

    expect(searchTitles('neuland'))->toBe([])
        ->and(searchTitles('altland'))->toBe(['Altland']);

    $new->delete();

    expect(searchTitles('altland'))->toBe([]);
});

test('a small catalogue is handed to the browser as a search index it may cache', function () {
    $this->get(route('shop.home', ['locale' => 'de']))
        ->assertInertia(function (Assert $page) use (&$url) {
            $url = $page->toArray()['props']['search_index_url'];
        });

    expect($url)->toStartWith(route('shop.search-index', ['locale' => 'de']).'?v=');

    $response = $this->getJson($url)->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('max-age=86400')
        ->and($response->json())->toHaveCount(2)
        // Catalogue order, text already folded for searching, cards as on the catalogue page.
        ->and($response->json('0'))->toMatchArray([
            'title' => 'zurich bauen building zurich',
            'authors' => 'jonas weber, anna beispiel',
            'codes' => '978-3-00-000002-8 9783000000028 si-2',
            'text' => 'uber raum und stadt.',
        ])
        ->and($response->json('0.card'))->toMatchArray(['title' => 'Zürich bauen', 'url' => $this->zurich->urlFor('de'), 'price_cents' => $this->zurich->price_cents])
        ->and(json_encode($response->json()))->not->toContain('unsichtbar');
});

test('the index address changes with the catalogue and with the customer group', function () {
    $urlFor = function (?User $user = null): string {
        $url = '';
        ($user ? $this->actingAs($user) : $this)->get(route('shop.home', ['locale' => 'de']))
            ->assertInertia(function (Assert $page) use (&$url) {
                $url = $page->toArray()['props']['search_index_url'];
            });

        return $url;
    };

    $before = $urlFor();

    expect($urlFor())->toBe($before);

    // A sale changes the stock shown on the cards.
    $this->zurich->decrement('stock');
    $afterSale = $urlFor();

    expect($afterSale)->not->toBe($before);

    $trade = CustomerGroup::factory()->create(['prices_include_tax' => false, 'is_default' => false]);
    $this->zurich->prices()->create(['customer_group_id' => $trade->id, 'price_cents' => 1000]);
    $dealer = User::factory()->create(['customer_group_id' => $trade->id]);

    $dealerUrl = $urlFor($dealer);

    // A new group price is a catalogue change too; the dealer gets an address of their own.
    expect($dealerUrl)->not->toBe($afterSale)
        ->toEndWith('&g='.$trade->id)
        ->and($this->actingAs($dealer)->getJson($dealerUrl)->json('0.card.price_cents'))->toBe(935);
});

test('a large catalogue is searched on the server only', function () {
    config(['shop.search.browser_index_max' => 1]);

    $this->get(route('shop.home', ['locale' => 'de']))
        ->assertInertia(fn (Assert $page) => $page->where('search_index_url', null));
    $this->getJson(route('shop.search-index', ['locale' => 'de']))->assertNotFound();

    expect(searchTitles('zurich'))->toBe(['Zürich bauen']);
});

test('the admin does not compute a storefront search index', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('search_index_url', null));
});

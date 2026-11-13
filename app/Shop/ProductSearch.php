<?php

namespace App\Shop;

use App\Http\Resources\ProductCardResource;
use App\Models\CustomerGroup;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Catalogue search and author filter over a precomputed index.
 *
 * The index holds, per product, the searchable text already normalised
 * (lower case, accents and umlauts folded, ISBN with and without hyphens). It
 * is cached forever and thrown away whenever a product is saved or deleted
 * (see Product::booted), so a search is a scan of a few strings and loads
 * only the products of the page it shows. Visibility (active, published) is
 * decided at search time, because "published" depends on the clock.
 *
 * Every word of the query has to match somewhere; title, author and ISBN
 * hits rank above description hits.
 *
 * Where the search runs depends on the size of the catalogue: up to
 * config('shop.search.browser_index_max') issues the browser downloads the
 * index once and searches locally (resources/js/lib/search-index.ts mirrors
 * score() and normalize()); above that every search is a request to ids().
 *
 * ponytail: linear scan of one cached array, measured at 0.4 ms for 1,500
 * issues and 3 ms for 10,000. Move to a database full-text index beyond that.
 */
class ProductSearch
{
    public const string CACHE_KEY = 'catalogue:search-index';

    private const string VERSION_KEY = 'catalogue:version';

    /**
     * Ids of the visible products matching the query and author, best first;
     * without a query in catalogue order (newest first).
     *
     * @return list<int>
     */
    public static function ids(string $query, string $author, string $locale): array
    {
        $words = array_values(array_filter(explode(' ', self::normalize($query))));
        $author = self::normalize($author);
        $now = now()->getTimestamp();
        $hits = [];

        foreach (self::index() as $position => $entry) {
            if (! $entry['active'] || ($entry['published_at'] !== null && $entry['published_at'] > $now)) {
                continue;
            }

            if ($author !== '' && ! in_array($author, $entry['author_list'], true)) {
                continue;
            }

            $score = self::score($entry, $words, $locale);

            if ($score !== null) {
                // Equal scores keep the catalogue order.
                $hits[] = [$score, $position, $entry['id']];
            }
        }

        usort($hits, fn (array $a, array $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_column($hits, 2);
    }

    /**
     * The authors field is one comma-separated string.
     *
     * @return list<string>
     */
    public static function authors(Product $product): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', (string) ($product->specs['authors'] ?? '')))));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forever(self::VERSION_KEY, (string) hrtime(true));
    }

    /**
     * Changes with every change to the catalogue; part of the browser index URL.
     */
    public static function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn (): string => (string) hrtime(true));
    }

    /**
     * Whether the catalogue is small enough to be searched in the browser.
     * Above the limit the storefront asks the server for every search.
     */
    public static function fitsInBrowser(): bool
    {
        return count(self::ids('', '', config('shop.locales')[0])) <= config('shop.search.browser_index_max');
    }

    /**
     * Visible issues in catalogue order, as shown on the catalogue page
     * (prices of the given customer group) plus their normalised search text.
     * Cached per catalogue version; ten minutes at most, so an issue scheduled
     * for publication appears without anyone touching the catalogue.
     *
     * @return list<array{card: array<string, mixed>, title: string, authors: string, codes: string, text: string}>
     */
    public static function clientIndex(string $locale, CustomerGroup $group): array
    {
        return Cache::remember(implode(':', ['catalogue:browser-index', self::version(), $locale, $group->id]), now()->addMinutes(10), function () use ($locale): array {
            $entries = collect(self::index())->keyBy('id');
            $ids = self::ids('', '', $locale);
            $products = Product::query()->with('prices')->findMany($ids)->keyBy('id');

            return array_map(fn (int $id): array => [
                'card' => ProductCardResource::make($products[$id])->resolve(),
                'title' => $entries[$id]['title'],
                'authors' => $entries[$id]['authors'],
                'codes' => $entries[$id]['codes'],
                'text' => $entries[$id]['text'][$locale] ?? '',
            ], $ids);
        });
    }

    /**
     * @param  array{title: string, authors: string, codes: string, text: array<string>}  $entry
     * @param  list<string>  $words
     */
    private static function score(array $entry, array $words, string $locale): ?int
    {
        $fields = [
            [10, $entry['title']],
            [6, $entry['authors']],
            [6, $entry['codes']],
            [1, $entry['text'][$locale] ?? ''],
        ];
        $score = 0;

        foreach ($words as $word) {
            $best = 0;

            foreach ($fields as [$weight, $text]) {
                if (str_contains($text, $word)) {
                    $best = max($best, $weight);
                }
            }

            if ($best === 0) {
                return null;
            }

            $score += $best;
        }

        return $score;
    }

    /**
     * @return array<int, array{id: int, active: bool, published_at: int|null, title: string, authors: string, author_list: list<string>, codes: string, text: array<string>}>
     */
    private static function index(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Product::query()
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->map(function (Product $product): array {
                $specs = $product->specs ?? [];
                $isbn = (string) ($specs['isbn'] ?? '');
                $text = [];

                foreach (config('shop.locales') as $locale) {
                    $text[$locale] = self::normalize($product->translate('description', $locale).' '.($specs['publisher'] ?? ''));
                }

                return [
                    'id' => $product->id,
                    'active' => $product->is_active,
                    'published_at' => $product->published_at?->getTimestamp(),
                    'title' => self::normalize(implode(' ', $product->title)),
                    'authors' => self::normalize((string) ($specs['authors'] ?? '')),
                    'author_list' => array_map(self::normalize(...), self::authors($product)),
                    'codes' => self::normalize($isbn.' '.str_replace('-', '', $isbn).' '.$product->sku),
                    'text' => $text,
                ];
            })
            ->all());
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::lower(Str::ascii($text))));
    }
}

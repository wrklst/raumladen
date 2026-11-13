<?php

namespace App\Seo;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * schema.org JSON-LD builders. Prices are the default (consumer) gross prices.
 */
class StructuredData
{
    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        $seller = config('shop.seller');

        return array_filter([
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => $seller['name'],
            'url' => url('/'),
            'email' => $seller['email'],
            'telephone' => $seller['phone'] ?: null,
            'vatID' => $seller['vat_id'] ?: null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $seller['address1'],
                'postalCode' => $seller['zip'],
                'addressLocality' => $seller['city'],
                'addressCountry' => $seller['country'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(string $locale): array
    {
        return [
            '@type' => 'WebSite',
            'url' => route('shop.home', ['locale' => $locale]),
            'name' => config('shop.seller.name'),
            'inLanguage' => $locale,
            'publisher' => ['@id' => url('/').'#organization'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(Product $product, string $locale, ?int $priceCents = null): array
    {
        $specs = $product->specs ?? [];
        $isbn = isset($specs['isbn']) ? preg_replace('/\D/', '', $specs['isbn']) : null;

        return array_filter([
            '@type' => ['Product', $product->type->schemaType()],
            '@id' => $product->urlFor($locale),
            'name' => $product->translate('title', $locale),
            'description' => $product->translate('description', $locale),
            'image' => $product->imageUrl(),
            'sku' => $product->sku,
            'isbn' => $isbn,
            'gtin13' => $isbn !== null && strlen($isbn) === 13 ? $isbn : null,
            'author' => isset($specs['authors']) ? array_map(
                fn (string $name) => ['@type' => 'Person', 'name' => trim($name)],
                explode(',', $specs['authors'])
            ) : null,
            'publisher' => ['@type' => 'Organization', 'name' => $specs['publisher'] ?? config('shop.seller.name')],
            'numberOfPages' => isset($specs['pages']) ? (int) $specs['pages'] : null,
            'bookFormat' => match (strtolower((string) ($specs['binding'] ?? ''))) {
                'hardcover', 'gebunden' => 'https://schema.org/Hardcover',
                'paperback', 'softcover', 'broschur', 'taschenbuch' => 'https://schema.org/Paperback',
                'ebook', 'e-book' => 'https://schema.org/EBook',
                default => null,
            },
            'inLanguage' => $specs['language'] ?? null,
            'datePublished' => $specs['published'] ?? null,
            'brand' => ['@type' => 'Brand', 'name' => $specs['publisher'] ?? config('shop.seller.name')],
            'offers' => [
                '@type' => 'Offer',
                'url' => $product->urlFor($locale),
                'priceCurrency' => $product->currency,
                'price' => Money::decimal($priceCents ?? $product->price_cents),
                'availability' => $product->hasStockFor(1) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => url('/').'#organization'],
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, mixed>
     */
    public static function itemList(Collection $products, string $locale): array
    {
        return [
            '@type' => 'ItemList',
            'itemListElement' => $products->values()->map(fn (Product $product, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $product->urlFor($locale),
                'name' => $product->translate('title', $locale),
            ])->all(),
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * Wrap nodes into one @graph document.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    public static function graph(array $nodes): array
    {
        return ['@context' => 'https://schema.org', '@graph' => $nodes];
    }
}

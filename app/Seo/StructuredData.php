<?php

namespace App\Seo;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Arr;
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
            'logo' => asset('apple-touch-icon.png'),
            'sameAs' => config('shop.site.same_as') ?: null,
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
            'hasMerchantReturnPolicy' => [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => $seller['country'],
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => config('shop.returns.days'),
                'returnMethod' => 'https://schema.org/ReturnByMail',
                'returnFees' => 'https://schema.org/ReturnFeesCustomerResponsibility',
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
            'name' => config('app.name'),
            'description' => __('Space Issues is a series of books published at the Sandberg Instituut, Amsterdam, initiated by Something Fantastic and Ludwig Engel. Order online, shipped from Germany.', [], $locale),
            'inLanguage' => $locale,
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('shop.home', ['locale' => $locale]).'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
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
                'shippingDetails' => self::shippingDetails($product->currency),
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Shipping to the home country, the only zone with a promised delivery time.
     *
     * @return array<string, mixed>|null
     */
    private static function shippingDetails(string $currency): ?array
    {
        $home = config('shop.tax.home_country');
        $zone = Arr::first(config('shop.shipping'), fn (array $zone) => $zone['code'] === $home);

        if ($zone === null) {
            return null;
        }

        return array_filter([
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => Money::decimal($zone['price_cents']), 'currency' => $currency],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => $home],
            'deliveryTime' => isset($zone['delivery_days']) ? [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => $zone['delivery_days'][0], 'maxValue' => $zone['delivery_days'][1], 'unitCode' => 'DAY'],
            ] : null,
        ]);
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

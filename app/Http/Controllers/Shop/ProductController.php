<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use App\Seo\Seo;
use App\Seo\StructuredData;
use App\Shop\ProductSearch;
use App\Theme\Theme;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Product $product): Response
    {
        $product->load('prices');
        $locale = app()->getLocale();

        $alternates = [];

        foreach (config('shop.locales') as $alternate) {
            if (! empty($product->slug[$alternate])) {
                $alternates[$alternate] = $product->urlFor($alternate);
            }
        }

        // The catalogue's order, newest first: the neighbours of this product, and what else can be ordered.
        // ponytail: looks at the 60 products nearest in the catalogue for the twelve others; widen when a catalogue outgrows that.
        $ids = ProductSearch::ids('', '', $locale);
        $at = (int) array_search($product->id, $ids, true);
        $nearby = array_slice($ids, max(0, $at - 30), 61);
        $others = Product::query()->with('prices')->findMany($nearby)->keyBy('id');
        $card = fn (?int $id): ?array => isset($others[$id]) ? ProductCardResource::make($others[$id])->resolve() : null;

        return Inertia::render('shop/product', [
            'product' => new ProductDetailResource($product),
            'next' => $card($ids[$at - 1] ?? null),
            'previous' => $card($ids[$at + 1] ?? null),
            'available' => ProductCardResource::collection(
                collect($nearby)->map(fn (int $id) => $others[$id] ?? null)->filter()
                    ->reject(fn (Product $other): bool => $other->is($product) || ! $other->hasStockFor(1))
                    ->take(12)->values(),
            )->resolve(),
            'manufacturer' => implode(', ', array_filter([
                config('shop.seller.name'),
                config('shop.seller.address1'),
                config('shop.seller.zip').' '.config('shop.seller.city'),
                config('shop.seller.email'),
            ])),
            'delivery_days' => Arr::first(config('shop.shipping'), fn (array $zone) => $zone['code'] === config('shop.tax.home_country'))['delivery_days'] ?? null,
            'seo' => Seo::make(
                title: $product->translate('title'),
                description: $product->descriptionText(),
                canonical: $product->urlFor($locale),
                alternates: $alternates,
                jsonLd: [
                    StructuredData::product($product, $locale),
                    StructuredData::breadcrumbs([
                        ['name' => config('shop.seller.name'), 'url' => route('shop.home')],
                        ['name' => $product->translate('title'), 'url' => $product->urlFor($locale)],
                    ]),
                ],
                image: is_file(Theme::current()->socialImagePath())
                    ? route('shop.products.social-image', ['product' => $product->translate('slug'), 'v' => $product->updated_at->timestamp])
                    : $product->imageUrl(),
                type: 'product',
            ),
        ]);
    }
}

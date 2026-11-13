<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use App\Seo\Seo;
use App\Seo\StructuredData;
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

        return Inertia::render('shop/product', [
            'product' => new ProductDetailResource($product),
            'manufacturer' => implode(', ', array_filter([
                config('shop.seller.name'),
                config('shop.seller.address1'),
                config('shop.seller.zip').' '.config('shop.seller.city'),
                config('shop.seller.email'),
            ])),
            'delivery_days' => Arr::first(config('shop.shipping'), fn (array $zone) => $zone['code'] === config('shop.tax.home_country'))['delivery_days'] ?? null,
            'seo' => Seo::make(
                title: $product->translate('title'),
                description: $product->translate('description'),
                canonical: $product->urlFor($locale),
                alternates: $alternates,
                jsonLd: [
                    StructuredData::product($product, $locale),
                    StructuredData::breadcrumbs([
                        ['name' => config('shop.seller.name'), 'url' => route('shop.home')],
                        ['name' => $product->translate('title'), 'url' => $product->urlFor($locale)],
                    ]),
                ],
                image: $product->imageUrl(),
                type: 'product',
            ),
        ]);
    }
}

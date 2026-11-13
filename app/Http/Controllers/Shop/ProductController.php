<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use App\Seo\Seo;
use App\Seo\StructuredData;
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
            'seo' => Seo::make(
                title: $product->translate('title').' – '.config('shop.seller.name'),
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

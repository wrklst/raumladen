<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

/**
 * Product feed for Google Merchant Center and for Meta catalogues, which
 * read the same format (RSS 2.0 with the g: namespace).
 * Consumer gross prices in EUR; shipping from the DE zone.
 */
class FeedController extends Controller
{
    public function show(string $channel, string $locale): Response
    {
        // Each channel is switched on in the backend (Shop settings, Channels).
        abort_unless(config("shop.channels.{$channel}_feed") && in_array($locale, config('shop.locales'), true), 404);

        app()->setLocale($locale);

        $zone = Arr::first(config('shop.shipping'), fn (array $zone) => $zone['code'] === 'DE');

        $items = Product::query()->active()->orderBy('id')->get()
            ->filter(fn (Product $product) => ! empty($product->slug[$locale]))
            ->map(function (Product $product) use ($locale) {
                $isbn = isset($product->specs['isbn']) ? preg_replace('/\D/', '', $product->specs['isbn']) : null;

                return [
                    'id' => $product->sku ?: 'P'.$product->id,
                    'title' => $product->translate('title', $locale),
                    'description' => $product->translate('description', $locale) ?? $product->translate('title', $locale),
                    'link' => $product->urlFor($locale),
                    'image_link' => $product->imageUrl(),
                    'availability' => $product->hasStockFor(1) ? 'in_stock' : 'out_of_stock',
                    'price' => Money::decimal($product->price_cents).' '.$product->currency,
                    'gtin' => $isbn !== null && strlen($isbn) === 13 ? $isbn : null,
                    'brand' => $product->specs['publisher'] ?? config('shop.seller.name'),
                    'google_product_category' => $product->type->googleProductCategory(),
                    'shipping_weight' => $product->weight_grams ? $product->weight_grams.' g' : null,
                ];
            });

        return response()
            ->view('seo.google-feed', [
                'items' => $items,
                'locale' => $locale,
                'shipping' => $zone ? ['country' => 'DE', 'price' => Money::decimal($zone['price_cents']).' EUR'] : null,
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}

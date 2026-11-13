<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use App\Seo\Seo;
use App\Seo\StructuredData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $products = Product::query()
            ->active()
            ->with('prices')
            ->latest('published_at')
            ->latest('id')
            ->paginate(24);

        // Resource::collection() replaces the paginator's models with resources, so build JSON-LD first.
        $itemList = StructuredData::itemList($products->getCollection(), app()->getLocale());

        return Inertia::render('shop/home', [
            'products' => ProductCardResource::collection($products),
            'seo' => Seo::make(
                title: config('shop.seller.name'),
                description: __('Books from :name. Order online, shipped from Germany.', ['name' => config('shop.seller.name')]),
                canonical: route('shop.home'),
                alternates: Seo::alternates('shop.home'),
                jsonLd: [$itemList],
            ),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use App\Seo\Seo;
use App\Seo\StructuredData;
use App\Shop\ProductSearch;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalogue: all issues, a search (?q=) or one author's issues (?author=).
 */
class HomeController extends Controller
{
    private const int PER_PAGE = 24;

    public function __invoke(Request $request): Response
    {
        $query = trim($request->string('q')->limit(100, '')->toString());
        $author = trim($request->string('author')->limit(100, '')->toString());
        $locale = app()->getLocale();

        // The cached index decides which issues and in which order; only this page's are loaded.
        $ids = ProductSearch::ids($query, $author, $locale);

        $filters = array_filter(['q' => $query, 'author' => $author]);
        $page = max(1, $request->integer('page', 1));
        $pageIds = array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $products = new LengthAwarePaginator(
            Product::query()->with('prices')->findMany($pageIds)->sortBy(fn (Product $product) => array_search($product->id, $pageIds, true))->values(),
            count($ids),
            self::PER_PAGE,
            $page,
            ['path' => route('shop.home'), 'query' => $filters],
        );

        // Resource::collection() replaces the paginator's models with resources, so build JSON-LD first.
        $itemList = StructuredData::itemList($products->getCollection(), $locale);

        return Inertia::render('shop/home', [
            'products' => ProductCardResource::collection($products),
            'filters' => ['q' => $query, 'author' => $author],
            'seo' => Seo::make(
                title: match (true) {
                    $query !== '' => __('Search: :query', ['query' => $query]),
                    $author !== '' => $author,
                    default => __('Books'),
                },
                description: $author !== '' && $query === ''
                    ? __('All issues by :author at :shop.', ['author' => $author, 'shop' => config('app.name')])
                    : Seo::siteDescription(),
                canonical: route('shop.home', array_filter([...($query === '' ? ['author' => $author] : []), 'page' => $page > 1 ? $page : null])),
                alternates: $query === '' ? Seo::alternates('shop.home', array_filter(['author' => $author])) : [],
                jsonLd: [$itemList],
                // Search results are endless variations of the same list: not for the index.
                index: $query === '',
            ),
        ]);
    }
}

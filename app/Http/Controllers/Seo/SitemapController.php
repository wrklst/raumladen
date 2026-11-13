<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemaps = array_map(fn (string $locale) => route('seo.sitemap.locale', $locale), config('shop.locales'));

        return response()
            ->view('seo.sitemap-index', ['sitemaps' => $sitemaps])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function locale(string $locale): Response
    {
        abort_unless(in_array($locale, config('shop.locales'), true), 404);

        $locales = config('shop.locales');
        $urls = [];

        $urls[] = [
            'loc' => route('shop.home', ['locale' => $locale]),
            'lastmod' => null,
            'alternates' => collect($locales)->mapWithKeys(fn ($l) => [$l => route('shop.home', ['locale' => $l])])->all(),
        ];

        Product::query()->active()->orderBy('id')->each(function (Product $product) use (&$urls, $locale, $locales): void {
            if (empty($product->slug[$locale])) {
                return;
            }

            $urls[] = [
                'loc' => $product->urlFor($locale),
                'lastmod' => $product->updated_at?->toAtomString(),
                'alternates' => collect($locales)
                    ->filter(fn ($l) => ! empty($product->slug[$l]))
                    ->mapWithKeys(fn ($l) => [$l => $product->urlFor($l)])
                    ->all(),
            ];
        });

        Page::query()->where('is_published', true)->orderBy('id')->each(function (Page $page) use (&$urls, $locale, $locales): void {
            $urls[] = [
                'loc' => route('shop.pages.show', ['locale' => $locale, 'page' => $page]),
                'lastmod' => $page->updated_at?->toAtomString(),
                'alternates' => collect($locales)->mapWithKeys(fn ($l) => [$l => route('shop.pages.show', ['locale' => $l, 'page' => $page])])->all(),
            ];
        });

        return response()
            ->view('seo.sitemap', ['urls' => $urls, 'default' => $locales[0]])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}

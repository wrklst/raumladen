<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * robots.txt and llms.txt (plain-text catalogue for AI crawlers).
 */
class SeoTextController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /*/cart',
            'Disallow: /*/checkout',
            'Disallow: /*/account',
            'Disallow: /*/orders/',
            '',
            'Sitemap: '.route('seo.sitemap.index'),
            'Sitemap: '.route('seo.llms'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function llms(): Response
    {
        $default = config('shop.locales')[0];
        app()->setLocale($default);
        $seller = config('shop.seller');

        $out = [
            '# '.$seller['name'],
            '',
            '> '.__('Books from :name. Order online, shipped from Germany.', ['name' => $seller['name']]),
            '',
            '- '.__('Website').': '.route('shop.home', ['locale' => $default]),
            '- '.__('Languages').': '.implode(', ', config('shop.locales')),
            '- '.__('Contact').': '.$seller['email'],
            '- Sitemap: '.route('seo.sitemap.index'),
            '- Product feed: '.route('seo.feed.google', $default),
            '',
            '## '.__('Books'),
            '',
        ];

        Product::query()->active()->orderBy('id')->each(function (Product $product) use (&$out, $default): void {
            $specs = $product->specs ?? [];
            $meta = array_filter([
                isset($specs['authors']) ? __('Authors').': '.$specs['authors'] : null,
                isset($specs['isbn']) ? 'ISBN '.$specs['isbn'] : null,
                Money::format($product->price_cents, $product->currency, $default),
            ]);

            $out[] = '- ['.$product->translate('title', $default).']('.$product->urlFor($default).'): '
                .Str::limit(trim(strip_tags((string) $product->translate('description', $default))), 200, '…')
                .' ('.implode(', ', $meta).')';
        });

        $out[] = '';
        $out[] = '## '.__('Information');
        $out[] = '';

        Page::query()->where('is_published', true)->orderBy('id')->each(function (Page $page) use (&$out, $default): void {
            $out[] = '- ['.$page->translate('title', $default).']('.route('shop.pages.show', ['locale' => $default, 'page' => $page]).')';
        });

        return response(implode("\n", $out)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}

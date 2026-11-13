<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use App\Seo\Seo;
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
        // Not ready for the public yet: keep every crawler out.
        if (! config('shop.seo.indexable')) {
            return response("User-agent: *\nDisallow: /\n")->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /*/cart',
            'Disallow: /*/checkout',
            'Disallow: /*/account',
            'Disallow: /*/orders/',
            'Disallow: /*/address-suggestions',
            'Disallow: /*/search-index',
            '',
            'Sitemap: '.route('seo.sitemap.index'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function llms(): Response
    {
        abort_unless(config('shop.seo.indexable') && config('shop.seo.llms_txt'), 404);

        $default = config('shop.locales')[0];
        app()->setLocale($default);
        $seller = config('shop.seller');

        $out = [
            '# '.config('app.name'),
            '',
            '> '.Seo::siteDescription($default),
            '',
            ...array_map(fn (string $locale) => '- '.__('Website').' ('.$locale.'): '.route('shop.home', ['locale' => $locale]), config('shop.locales')),
            '- '.__('Publisher').': '.$seller['name'],
            '- '.__('Contact').': '.$seller['email'],
            '- Sitemap: '.route('seo.sitemap.index'),
            ...(config('shop.channels.google_feed') ? ['- Product feed: '.route('seo.feed', ['channel' => 'google', 'locale' => $default])] : []),
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
        $out[] = '## '.__('Shipping');
        $out[] = '';

        foreach (config('shop.shipping') as $zone) {
            $out[] = '- '.$zone['code'].': '.Money::format($zone['price_cents'], config('shop.currency'), $default)
                .($zone['free_from_cents'] === null ? '' : ', '.__('free from :amount', ['amount' => Money::format($zone['free_from_cents'], config('shop.currency'), $default)]))
                .(isset($zone['delivery_days']) ? ', '.__(':min–:max working days', ['min' => $zone['delivery_days'][0], 'max' => $zone['delivery_days'][1]]) : '');
        }

        $out[] = '- '.__('Right of withdrawal: :days days', ['days' => config('shop.returns.days')]);
        $out[] = '';
        $out[] = '## '.__('Information');
        $out[] = '';

        Page::query()->where('is_published', true)->orderBy('id')->each(function (Page $page) use (&$out, $default): void {
            $out[] = '- ['.$page->translate('title', $default).']('.route('shop.pages.show', ['locale' => $default, 'page' => $page]).')';
        });

        return response(implode("\n", $out)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}

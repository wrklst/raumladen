<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Seo\Seo;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(Page $page): Response
    {
        abort_unless($page->is_published, 404);

        $title = (string) $page->translate('title');

        return Inertia::render('shop/page', [
            'page' => [
                'slug' => $page->slug,
                'title' => $title,
                // The page shows its title as the heading: a text that opens with the same heading would say it twice.
                'html' => preg_replace('/^\s*<h[1-6]>\s*'.preg_quote(e($title), '/').'\s*<\/h[1-6]>\s*/u', '', $page->renderedBody()),
            ],
            'seo' => Seo::make(
                title: $page->translate('title'),
                description: $page->renderedBody(),
                canonical: route('shop.pages.show', $page),
                alternates: Seo::alternates('shop.pages.show', ['page' => $page]),
            ),
        ]);
    }
}

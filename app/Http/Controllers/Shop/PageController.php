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

        return Inertia::render('shop/page', [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->translate('title'),
                'html' => $page->renderedBody(),
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

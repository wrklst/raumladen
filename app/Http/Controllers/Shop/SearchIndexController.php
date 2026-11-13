<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Shop\CurrentCustomer;
use App\Shop\ProductSearch;
use Illuminate\Http\JsonResponse;

/**
 * The catalogue as a search index for the browser: with a small catalogue
 * the storefront downloads it once and searches without asking the server.
 * The URL carries the catalogue version and the customer group (see
 * HandleInertiaRequests), so the browser may keep the answer for a day and
 * still gets a fresh one the moment a product or a price changes.
 */
class SearchIndexController extends Controller
{
    public function __invoke(): JsonResponse
    {
        abort_unless(ProductSearch::fitsInBrowser(), 404);

        return response()
            ->json(ProductSearch::clientIndex(app()->getLocale(), CurrentCustomer::group()))
            // private: prices depend on the customer group.
            ->header('Cache-Control', 'private, max-age=86400');
    }
}

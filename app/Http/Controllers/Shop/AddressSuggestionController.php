<?php

namespace App\Http\Controllers\Shop;

use App\Address\AddressSuggester;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The address field's way to Photon, through our server. Throttled in the
 * route: the limit is not against customers (a debounced field sends a
 * handful of requests per address) but against scripts using this as a free
 * geocoder on komoot's fair-use budget.
 */
class AddressSuggestionController extends Controller
{
    public function __invoke(Request $request, AddressSuggester $suggester): JsonResponse
    {
        return response()->json([
            'suggestions' => $suggester->suggest(
                $request->string('q')->toString(),
                $request->string('country')->toString(),
                app()->getLocale(),
            ),
            'attribution' => AddressSuggester::ATTRIBUTION,
        ]);
    }
}

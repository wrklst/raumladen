<?php

namespace App\Http\Controllers;

use App\Analytics\SiteVisits;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /site-events: the storefront's batched, cookieless beacon.
 *
 * The route is registered outside the web middleware (bootstrap/app.php): no
 * session, no cookie in either direction, no CSRF token. The body arrives as
 * text/plain and is parsed by hand. A beacon is never answered with an
 * error: anything unusable is dropped and answered 204.
 */
class SiteEventController extends Controller
{
    private const int MAX_BODY_BYTES = 32768;

    public function __invoke(Request $request): Response
    {
        $body = $request->getContent();
        $payload = strlen($body) <= self::MAX_BODY_BYTES ? json_decode($body, true) : null;

        if (is_array($payload)) {
            SiteVisits::record($payload, $request);
        }

        return response()->noContent();
    }
}

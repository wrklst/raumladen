<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale for the routes outside the /{locale} storefront group (admin, login,
 * settings ...): the user's choice, the session, the browser, the default.
 */
class SetBackendLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route('locale') === null) {
            $locales = config('shop.locales');

            $candidates = [
                $request->user()?->locale,
                $request->hasSession() ? $request->session()->get('locale') : null,
                $request->getPreferredLanguage($locales),
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && in_array($candidate, $locales, true)) {
                    app()->setLocale($candidate);

                    break;
                }
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! in_array($locale, config('shop.locales'), true)) {
            // A language the shop speaks but this storefront has switched off:
            // old links and bookmarks land on the same page in the default language.
            abort_unless(in_array($locale, config('shop.supported_locales'), true), 404);

            $default = '/'.config('shop.locales')[0];

            return redirect()->to(preg_replace('#^/?'.$locale.'(?=/|$)#', $default, $request->getRequestUri(), 1) ?? $default);
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->route()->forgetParameter('locale');
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}

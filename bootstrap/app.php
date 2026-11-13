<?php

use App\Http\Controllers\SiteEventController;
use App\Http\Middleware\EnsureStaffCan;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetBackendLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The statistics beacon stays outside the web middleware: no session, no cookie, no CSRF token.
        then: fn () => Route::post('site-events', SiteEventController::class)->middleware('throttle:60,1')->name('site-events'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            SetBackendLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias(['staff' => EnsureStaffCan::class]);

        // The locale has to be known before authentication (the guest redirect
        // goes to the login page of that locale) and before route model
        // binding (products are bound by the slug of the request locale).
        $middleware->prependToPriorityList(AuthenticatesRequests::class, SetLocale::class);

        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin*') || $request->is('settings*') || $request->is('dashboard') || $request->is('user/*')) {
                return route('login');
            }

            return route('shop.account.login', ['locale' => $request->session()->get('locale', config('shop.locales')[0])]);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

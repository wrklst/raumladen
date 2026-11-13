<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Seo\FeedController;
use App\Http\Controllers\Seo\SeoTextController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $locales = config('shop.locales');
    $locale = $request->session()->get('locale') ?? $request->getPreferredLanguage($locales) ?? $locales[0];

    return redirect()->route('shop.home', ['locale' => in_array($locale, $locales, true) ? $locale : $locales[0]]);
})->name('home');

Route::get('dashboard', function (Request $request) {
    return $request->user()->isStaff()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('shop.account.orders.index', ['locale' => $request->user()->locale ?? config('shop.locales')[0]]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::post('locale', LocaleController::class)->name('locale.update');

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('seo.sitemap.index');
Route::get('sitemap-{locale}.xml', [SitemapController::class, 'locale'])->name('seo.sitemap.locale');
Route::get('feeds/{channel}-{locale}.xml', [FeedController::class, 'show'])->whereIn('channel', ['google', 'meta'])->name('seo.feed');
Route::get('robots.txt', [SeoTextController::class, 'robots'])->name('seo.robots');
Route::get('llms.txt', [SeoTextController::class, 'llms'])->name('seo.llms');

Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)->name('webhooks.payments');

// Accepting an invitation to the backend; the address is signed and expires.
Route::middleware(['guest', 'signed', 'throttle:12,1'])->group(function () {
    Route::get('invitation/{user}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation/{user}', [InvitationController::class, 'store'])->name('invitation.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/shop.php';

<?php

use App\Http\Controllers\Account\LoginController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Shop\AddressSuggestionController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CartCouponController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderCompleteController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\PaymentController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SearchIndexController;
use App\Http\Controllers\Shop\WithdrawalController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}')
    ->where(['locale' => implode('|', config('shop.locales'))])
    ->middleware(SetLocale::class)
    ->name('shop.')
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('pages/{page}', [PageController::class, 'show'])->name('pages.show');

        Route::get('cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('cart', [CartController::class, 'store'])->name('cart.store');
        Route::post('cart/coupon', [CartCouponController::class, 'store'])->name('cart.coupon.store');
        Route::delete('cart/coupon', [CartCouponController::class, 'destroy'])->name('cart.coupon.destroy');
        Route::patch('cart/{product:id}', [CartController::class, 'update'])->name('cart.update');
        Route::delete('cart/{product:id}', [CartController::class, 'destroy'])->name('cart.destroy');

        Route::get('search-index', SearchIndexController::class)->name('search-index');
        Route::get('address-suggestions', AddressSuggestionController::class)->middleware('throttle:60,1')->name('address-suggestions');

        Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
        Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');

        Route::get('orders/{order}/payment', [PaymentController::class, 'return'])->name('payments.return');
        Route::post('orders/{order}/payment', [PaymentController::class, 'retry'])->name('payments.retry');
        Route::get('orders/{order}/complete', OrderCompleteController::class)->name('orders.complete');

        Route::get('withdraw', [WithdrawalController::class, 'create'])->name('withdrawal.create');
        Route::post('withdraw', [WithdrawalController::class, 'store'])->middleware('throttle:6,1')->name('withdrawal.store');

        Route::get('account/login', [LoginController::class, 'create'])->name('account.login');
        Route::post('account/login/code', [LoginController::class, 'sendCode'])->middleware('throttle:6,1')->name('account.login.code');
        Route::post('account/login/verify', [LoginController::class, 'verify'])->middleware('throttle:6,1')->name('account.login.verify');

        Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
            Route::get('/', [AccountOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
            Route::get('orders/{order}/invoices/{invoice}', [AccountOrderController::class, 'invoice'])->name('orders.invoice');
            Route::get('profile', [AccountProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('profile', [AccountProfileController::class, 'update'])->name('profile.update');
        });
    });

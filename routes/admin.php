<?php

use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DatevExportController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\OrderCancellationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderPaymentConfirmationController;
use App\Http\Controllers\Admin\OrderRefundController;
use App\Http\Controllers\Admin\OrderShipmentController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StatisticsController;
use Illuminate\Support\Facades\Route;

/*
 * Backend. Every route group names the area it belongs to; which role works
 * in which area is decided in App\Enums\UserRole::areas().
 */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'staff'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('staff:orders')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    });

    Route::post('orders/{order}/ship', [OrderShipmentController::class, 'store'])->middleware('staff:shipping')->name('orders.ship');

    Route::middleware('staff:payments')->group(function () {
        Route::post('orders/{order}/confirm-payment', [OrderPaymentConfirmationController::class, 'store'])->name('orders.confirm-payment');
        Route::post('orders/{order}/cancel', [OrderCancellationController::class, 'store'])->name('orders.cancel');
        Route::post('orders/{order}/refund', [OrderRefundController::class, 'store'])->name('orders.refund');
    });

    Route::resource('products', ProductController::class)->except('show')->parameters(['products' => 'product:id'])->middleware('staff:catalog');
    Route::resource('pages', PageController::class)->except('show')->middleware('staff:content');
    Route::resource('coupons', CouponController::class)->except('show')->middleware('staff:coupons');

    Route::middleware('staff:customers')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{user}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::patch('customers/{user}', [CustomerController::class, 'update'])->name('customers.update');
        Route::resource('customer-groups', CustomerGroupController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('staff:invoices')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    });

    Route::middleware('staff:datev')->group(function () {
        Route::get('datev', [DatevExportController::class, 'index'])->name('datev.index');
        Route::post('datev', [DatevExportController::class, 'store'])->name('datev.store');
        Route::get('datev/{datevExport}/download', [DatevExportController::class, 'download'])->name('datev.download');
    });

    Route::get('statistics', StatisticsController::class)->middleware('staff:statistics')->name('statistics');

    Route::middleware('staff:settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings/test-mail', [SettingsController::class, 'testMail'])->middleware('throttle:6,1')->name('settings.test-mail');
        Route::put('settings/{group}', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('staff/{user}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
        Route::post('staff/{user}/invite', [StaffController::class, 'invite'])->middleware('throttle:6,1')->name('staff.invite');
    });
});

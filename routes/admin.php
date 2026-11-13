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
use App\Http\Controllers\Admin\OrderShipmentController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('products', ProductController::class)->except('show')->parameters(['products' => 'product:id']);

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/ship', [OrderShipmentController::class, 'store'])->name('orders.ship');
    Route::post('orders/{order}/cancel', [OrderCancellationController::class, 'store'])->name('orders.cancel');
    Route::post('orders/{order}/confirm-payment', [OrderPaymentConfirmationController::class, 'store'])->name('orders.confirm-payment');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{user}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::patch('customers/{user}', [CustomerController::class, 'update'])->name('customers.update');

    Route::resource('coupons', CouponController::class)->except('show');
    Route::resource('customer-groups', CustomerGroupController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');

    Route::get('datev', [DatevExportController::class, 'index'])->name('datev.index');
    Route::post('datev', [DatevExportController::class, 'store'])->name('datev.store');
    Route::get('datev/{datevExport}/download', [DatevExportController::class, 'download'])->name('datev.download');

    Route::resource('pages', PageController::class)->except('show');
});

<?php

use App\Console\Commands\CancelUnpaidOrders;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Scheduling\Schedule;

/**
 * @param  array<string, mixed>  $attributes
 */
function pendingOrder(Product $product, int $quantity, int $ageInDays, array $attributes = []): Order
{
    $order = Order::factory()->create(['created_at' => now()->subDays($ageInDays), ...$attributes]);
    $order->items()->create([
        'product_id' => $product->id, 'name' => 'Buch', 'sku' => 'BK-1', 'quantity' => $quantity, 'unit_price_cents' => 1070,
        'tax_class' => 'reduced', 'tax_rate' => 7, 'tax_category' => 'S', 'discount_cents' => 0,
        'line_net_cents' => 1000, 'line_tax_cents' => 70, 'line_gross_cents' => 1070,
    ]);

    return $order;
}

function payWith(Order $order, string $gateway, PaymentStatus $status): void
{
    $order->payments()->create(['gateway' => $gateway, 'gateway_id' => 'x_'.$order->id, 'status' => $status, 'amount_cents' => 1070, 'currency' => 'EUR']);
}

test('an old pending order is cancelled and its stock returned, a recent one is left alone', function () {
    config(['shop.orders.unpaid_cancel_days' => 14]);
    $product = Product::factory()->create(['stock' => 1]);
    $old = pendingOrder($product, 2, 15);
    $recent = pendingOrder($product, 3, 13);

    $this->artisan('orders:cancel-unpaid')->expectsOutput('Cancelled 1 unpaid order(s).')->assertSuccessful();

    expect($old->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($old->cancelled_at)->not->toBeNull()
        ->and($recent->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($product->refresh()->stock)->toBe(3);
});

test('paid orders and untracked products are not touched wrongly', function () {
    $product = Product::factory()->create(['stock' => 1]);
    $untracked = Product::factory()->create(['stock' => 1, 'track_stock' => false]);
    $paid = pendingOrder($product, 2, 30, ['status' => OrderStatus::Paid, 'paid_at' => now()]);
    $old = pendingOrder($untracked, 2, 30);

    $this->artisan('orders:cancel-unpaid')->assertSuccessful();

    expect($paid->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($old->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->refresh()->stock)->toBe(1)
        ->and($untracked->refresh()->stock)->toBe(1);
});

test('orders with money on the way are skipped, an unconfirmed bank transfer is not', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $paidPayment = pendingOrder($product, 1, 30);
    payWith($paidPayment, 'mollie', PaymentStatus::Paid);
    $pendingMollie = pendingOrder($product, 1, 30);
    payWith($pendingMollie, 'mollie', PaymentStatus::Pending);
    $abandonedMollie = pendingOrder($product, 1, 30);
    payWith($abandonedMollie, 'mollie', PaymentStatus::Open);
    $transfer = pendingOrder($product, 1, 30);
    payWith($transfer, 'bank_transfer', PaymentStatus::Pending);

    $this->artisan('orders:cancel-unpaid')->assertSuccessful();

    expect($paidPayment->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($pendingMollie->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($abandonedMollie->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($transfer->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->refresh()->stock)->toBe(2);
});

test('running the command twice returns the stock only once', function () {
    $product = Product::factory()->create(['stock' => 0]);
    pendingOrder($product, 2, 20);

    $this->artisan('orders:cancel-unpaid')->assertSuccessful();
    $this->artisan('orders:cancel-unpaid')->expectsOutput('Cancelled 0 unpaid order(s).')->assertSuccessful();

    expect($product->refresh()->stock)->toBe(2);
});

test('the command runs daily', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'orders:cancel-unpaid'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 0 * * *')
        ->and(app(CancelUnpaidOrders::class)->getName())->toBe('orders:cancel-unpaid');
});

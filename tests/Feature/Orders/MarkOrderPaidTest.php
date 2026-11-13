<?php

use App\Enums\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Orders\MarkOrderPaid;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
});

function unpaidOrderWithItem(array $attributes = []): Order
{
    $order = Order::factory()->create($attributes);
    $order->items()->create([
        'name' => 'Testbuch', 'sku' => 'BK-1', 'quantity' => 1, 'unit_price_cents' => 1070, 'tax_class' => 'reduced',
        'tax_rate' => 7, 'tax_category' => 'S', 'discount_cents' => 0, 'line_net_cents' => 1000, 'line_tax_cents' => 70, 'line_gross_cents' => 1070,
    ]);

    return $order;
}

test('marking an order paid stores the payment, issues the invoice and mails the customer', function () {
    $order = unpaidOrderWithItem(['email' => 'kunde@example.com', 'locale' => 'en']);

    app(MarkOrderPaid::class)->handle($order, 'mollie');

    $order->refresh();
    $invoice = $order->invoices()->sole();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payment_gateway)->toBe('mollie')
        ->and($invoice->number)->toBe('RE-00001')
        ->and($invoice->total_gross_cents)->toBe(1070);
    Storage::disk('local')->assertExists($invoice->pdf_path);
    Mail::assertSent(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->hasTo('kunde@example.com')
        && $mail->invoice->is($invoice)
        && $mail->locale === 'en');
});

test('a second notification issues no second invoice and sends no second mail', function () {
    $order = unpaidOrderWithItem();
    $stale = Order::query()->findOrFail($order->id);

    app(MarkOrderPaid::class)->handle($order, 'mollie');
    $paidAt = $order->refresh()->paid_at;

    $this->travel(5)->minutes();
    app(MarkOrderPaid::class)->handle($stale, 'bank_transfer');

    $order->refresh();

    expect($order->invoices()->count())->toBe(1)
        ->and($order->payment_gateway)->toBe('mollie')
        ->and($order->paid_at->equalTo($paidAt))->toBeTrue();
    Mail::assertSent(OrderConfirmation::class, 1);
});

test('an order that is already paid is left alone', function () {
    $order = Order::factory()->paid()->create();

    app(MarkOrderPaid::class)->handle($order, 'mollie');

    expect($order->invoices()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('a late payment notification does not resurrect a cancelled order', function () {
    $order = unpaidOrderWithItem(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

    app(MarkOrderPaid::class)->handle($order, 'mollie');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->invoices()->count())->toBe(0);
    Mail::assertNothingSent();
});

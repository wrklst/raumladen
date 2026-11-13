<?php

use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Invoicing\InvoiceIssuer;
use App\Mail\OrderConfirmation;
use App\Mail\OrderShipped;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Payments\MollieGateway;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\CreatePaymentRefundRequest;
use Mollie\Api\MollieApiClient;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    Storage::fake('local');
    Mail::fake();
    $this->actingAs(User::factory()->admin()->create());
});

function orderOf(Product $product, int $quantity = 1, array $attributes = []): Order
{
    $order = Order::factory()->create($attributes);
    $order->items()->create([
        'product_id' => $product->id, 'name' => 'Testbuch', 'sku' => 'BK-1', 'quantity' => $quantity, 'unit_price_cents' => 1070,
        'tax_class' => 'reduced', 'tax_rate' => 7, 'tax_category' => 'S', 'discount_cents' => 0,
        'line_net_cents' => 1000, 'line_tax_cents' => 70, 'line_gross_cents' => 1070,
    ]);

    return $order;
}

function paidOrderOf(Product $product, string $gateway, array $attributes = []): Order
{
    $order = orderOf($product, 2, ['status' => OrderStatus::Paid, 'paid_at' => now(), 'payment_gateway' => $gateway, ...$attributes]);
    $order->payments()->create([
        'gateway' => $gateway, 'gateway_id' => $gateway === 'mollie' ? 'tr_123' : $order->number, 'status' => PaymentStatus::Paid,
        'amount_cents' => $order->total_cents, 'currency' => 'EUR', 'paid_at' => now(),
    ]);
    app(InvoiceIssuer::class)->issueInvoice($order);

    return $order;
}

function fakeMollieRefund(MockResponse $response): MollieApiClient
{
    $client = MollieApiClient::fake([CreatePaymentRefundRequest::class => $response], retainRequests: true);
    $gateway = app(MollieGateway::class);
    (new ReflectionProperty($gateway, 'client'))->setValue($gateway, $client);
    app()->instance(MollieGateway::class, $gateway);

    return $client;
}

test('cancelling an unpaid order cancels it and returns the stock', function () {
    $product = Product::factory()->create(['stock' => 3]);
    $order = orderOf($product, 2);

    $this->post(route('admin.orders.cancel', $order))->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($order->invoices()->count())->toBe(0)
        ->and($product->refresh()->stock)->toBe(5);
});

test('cancelling leaves the stock of untracked products alone', function () {
    $product = Product::factory()->create(['stock' => 3, 'track_stock' => false]);

    $this->post(route('admin.orders.cancel', orderOf($product, 2)))->assertRedirect();

    expect($product->refresh()->stock)->toBe(3);
});

test('cancelling a paid bank-transfer order issues a credit note and marks it refunded', function () {
    $product = Product::factory()->create(['stock' => 3]);
    $order = paidOrderOf($product, 'bank_transfer');

    $this->post(route('admin.orders.cancel', $order))->assertRedirect();

    $order->refresh();
    $credit = $order->invoices()->where('type', InvoiceType::CreditNote)->sole();

    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($credit->number)->toBe('GS-00001')
        ->and($credit->references->number)->toBe('RE-00001')
        ->and($product->refresh()->stock)->toBe(5);
    Storage::disk('local')->assertExists($credit->pdf_path);
});

test('cancelling a shipped order is a refund as well', function () {
    $order = paidOrderOf(Product::factory()->create(), 'bank_transfer', ['status' => OrderStatus::Shipped, 'shipped_at' => now()]);

    $this->post(route('admin.orders.cancel', $order))->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Refunded)->and($order->invoices()->count())->toBe(2);
});

test('cancelling a paid Mollie order refunds the payment through the gateway', function () {
    config(['shop.payments.mollie.api_key' => 'test_abc']);
    $client = fakeMollieRefund(MockResponse::created([
        'resource' => 'refund', 'id' => 're_1', 'status' => 'queued', 'amount' => ['value' => '10.70', 'currency' => 'EUR'],
        'paymentId' => 'tr_123', 'createdAt' => '2026-06-15T12:00:00+00:00', '_links' => [],
    ], 'refund'));
    $order = paidOrderOf(Product::factory()->create(), 'mollie');

    $this->post(route('admin.orders.cancel', $order))->assertRedirect();

    $payment = $order->payments()->sole();

    expect($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and($payment->refunded_cents)->toBe($order->total_cents)
        ->and($payment->status)->toBe(PaymentStatus::Refunded);
    $client->assertSentCount(1);
});

test('a failed gateway refund still leaves the credit note and the refunded order', function () {
    Exceptions::fake();
    config(['shop.payments.mollie.api_key' => 'test_abc']);
    fakeMollieRefund(MockResponse::error(500, 'Server Error', 'Mollie is down'));
    $order = paidOrderOf(Product::factory()->create(), 'mollie');

    $this->post(route('admin.orders.cancel', $order))->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->invoices()->where('type', InvoiceType::CreditNote)->count())->toBe(1)
        ->and($order->payments()->sole()->refunded_cents)->toBe(0);
    Exceptions::assertReportedCount(1);
});

test('an order that is already cancelled or refunded cannot be cancelled again', function (OrderStatus $status) {
    $product = Product::factory()->create(['stock' => 3]);
    $order = orderOf($product, 1, ['status' => $status]);

    $this->post(route('admin.orders.cancel', $order))->assertStatus(422);

    expect($product->refresh()->stock)->toBe(3)->and($order->invoices()->count())->toBe(0);
})->with([OrderStatus::Cancelled, OrderStatus::Refunded]);

test('only a paid order can be shipped, and the customer is told', function () {
    $order = Order::factory()->paid()->create(['email' => 'kunde@example.com', 'locale' => 'en']);

    $this->post(route('admin.orders.ship', $order))->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Shipped)->and($order->shipped_at)->not->toBeNull();
    Mail::assertSent(OrderShipped::class, fn (OrderShipped $mail) => $mail->hasTo('kunde@example.com') && $mail->locale === 'en');
});

test('orders in any other state cannot be shipped', function (OrderStatus $status) {
    $order = Order::factory()->create(['status' => $status]);

    $this->post(route('admin.orders.ship', $order))->assertStatus(422);

    expect($order->refresh()->status)->toBe($status)->and($order->shipped_at)->toBeNull();
    Mail::assertNothingSent();
})->with([OrderStatus::Pending, OrderStatus::Shipped, OrderStatus::Cancelled, OrderStatus::Refunded]);

test('confirming a bank transfer pays the order, issues the invoice and mails it', function () {
    $order = orderOf(Product::factory()->create());
    $order->payments()->create(['gateway' => 'bank_transfer', 'gateway_id' => $order->number, 'status' => PaymentStatus::Pending, 'amount_cents' => 1070, 'currency' => 'EUR']);

    $this->post(route('admin.orders.confirm-payment', $order))->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_gateway)->toBe('bank_transfer')
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Paid)
        ->and($order->invoices()->sole()->number)->toBe('RE-00001');
    Mail::assertSent(OrderConfirmation::class, 1);
});

test('confirming without a recorded payment creates the transfer payment on the fly', function () {
    $order = orderOf(Product::factory()->create());

    $this->post(route('admin.orders.confirm-payment', $order))->assertRedirect();

    $payment = $order->payments()->sole();

    expect($payment->gateway)->toBe('bank_transfer')
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->amount_cents)->toBe(1070)
        ->and($order->refresh()->isPaid())->toBeTrue();
});

test('confirming payment of an already paid order is rejected', function () {
    $order = paidOrderOf(Product::factory()->create(), 'mollie');

    $this->post(route('admin.orders.confirm-payment', $order))->assertStatus(422);

    expect($order->invoices()->count())->toBe(1);
    Mail::assertNothingSent();
});

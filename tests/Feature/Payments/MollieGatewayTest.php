<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderConfirmation;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Payments\MollieGateway;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\CreatePaymentRefundRequest;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\MollieApiClient;

/*
 * MollieGateway builds its own client and offers no seam, so the faked client
 * is written into its private $client property. The gateway instance is then
 * bound in the container so controllers and webhooks use it too.
 */
beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    config(['shop.payments.mollie.api_key' => 'test_abc']);
    Storage::fake('local');
    Mail::fake();
});

/**
 * @param  array<class-string, MockResponse>  $responses
 */
function mollieWith(array $responses): array
{
    $client = MollieApiClient::fake($responses, retainRequests: true);
    $gateway = app(MollieGateway::class);

    (new ReflectionProperty($gateway, 'client'))->setValue($gateway, $client);
    app()->instance(MollieGateway::class, $gateway);

    return [$gateway, $client];
}

function molliePayment(string $status, array $extra = [], bool $created = false): MockResponse
{
    return ($created ? MockResponse::created(...) : MockResponse::ok(...))([
        'resource' => 'payment',
        'id' => 'tr_123',
        'mode' => 'test',
        'status' => $status,
        'method' => 'creditcard',
        'amount' => ['value' => '10.70', 'currency' => 'EUR'],
        'description' => 'Test',
        'createdAt' => '2026-06-15T12:00:00+00:00',
        '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/tr_123', 'type' => 'text/html']],
        ...$extra,
    ], 'payment');
}

function openMollieOrder(): Order
{
    $order = Order::factory()->create();
    $order->payments()->create(['gateway' => 'mollie', 'gateway_id' => 'tr_123', 'status' => PaymentStatus::Open, 'amount_cents' => 1070, 'currency' => 'EUR']);

    return $order;
}

test('starting a payment creates it at Mollie and returns the checkout URL', function () {
    [$gateway, $client] = mollieWith([CreatePaymentRequest::class => molliePayment('open', created: true)]);
    $order = Order::factory()->create(['total_cents' => 1070, 'locale' => 'en']);

    $url = $gateway->start($order, 'https://shop.test/return', 'https://shop.test/hook');

    $payment = $order->payments()->sole();

    expect($url)->toBe('https://www.mollie.com/checkout/tr_123')
        ->and($payment->gateway_id)->toBe('tr_123')
        ->and($payment->status)->toBe(PaymentStatus::Open)
        ->and($payment->amount_cents)->toBe(1070);
    $client->assertSentCount(1);
});

test('syncing a paid payment pays the order and issues the invoice', function () {
    [$gateway] = mollieWith([GetPaymentRequest::class => molliePayment('paid', ['paidAt' => '2026-06-15T12:05:00+00:00'])]);
    $order = openMollieOrder();
    $payment = $order->payments()->sole();

    $gateway->sync($payment);

    $order->refresh();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->method)->toBe('creditcard')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_gateway)->toBe('mollie')
        ->and($order->invoices()->count())->toBe(1);
    Mail::assertSent(OrderConfirmation::class, 1);
});

test('remote statuses are mapped and only a paid status pays the order', function (string $remote, PaymentStatus $expected) {
    [$gateway] = mollieWith([GetPaymentRequest::class => molliePayment($remote)]);
    $order = openMollieOrder();

    $gateway->sync($order->payments()->sole());

    expect($order->payments()->sole()->status)->toBe($expected)
        ->and($order->refresh()->isPaid())->toBeFalse();
    Mail::assertNothingSent();
})->with([
    'open' => ['open', PaymentStatus::Open],
    'pending' => ['pending', PaymentStatus::Pending],
    'authorized' => ['authorized', PaymentStatus::Pending],
    'canceled' => ['canceled', PaymentStatus::Cancelled],
    'expired' => ['expired', PaymentStatus::Expired],
    'failed' => ['failed', PaymentStatus::Failed],
]);

test('a webhook for a known payment syncs it, and repeated webhooks pay only once', function () {
    mollieWith([GetPaymentRequest::class => molliePayment('paid')]);
    $order = openMollieOrder();

    $this->post(route('webhooks.payments', 'mollie'), ['id' => 'tr_123'])->assertOk();
    $this->post(route('webhooks.payments', 'mollie'), ['id' => 'tr_123'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->invoices()->count())->toBe(1);
    Mail::assertSent(OrderConfirmation::class, 1);
});

test('returning from Mollie after a successful payment lands on the confirmation', function () {
    mollieWith([GetPaymentRequest::class => molliePayment('paid')]);
    $order = openMollieOrder();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->get(route('shop.payments.return', ['locale' => 'de', 'order' => $order]))
        ->assertRedirect(route('shop.orders.complete', ['locale' => 'de', 'order' => $order]));

    expect($order->refresh()->isPaid())->toBeTrue();
});

test('returning from Mollie after a cancelled payment offers another attempt', function () {
    mollieWith([GetPaymentRequest::class => molliePayment('canceled')]);
    config(['shop.seller.iban' => 'DE02120300000000202051']);
    $order = openMollieOrder();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->get(route('shop.payments.return', ['locale' => 'de', 'order' => $order]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shop/payment-failed')
            ->where('gateways', ['mollie', 'bank_transfer']));

    expect($order->refresh()->isPaid())->toBeFalse();
});

test('a Mollie outage on return is reported but does not break the page', function () {
    mollieWith([GetPaymentRequest::class => MockResponse::error(500, 'Server Error', 'Mollie is down')]);
    $order = openMollieOrder();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->get(route('shop.payments.return', ['locale' => 'de', 'order' => $order]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('shop/payment-failed'));
});

test('refunding sends the amount to Mollie and records it', function () {
    [$gateway, $client] = mollieWith([CreatePaymentRefundRequest::class => MockResponse::created([
        'resource' => 'refund', 'id' => 're_1', 'status' => 'queued', 'amount' => ['value' => '10.70', 'currency' => 'EUR'],
        'paymentId' => 'tr_123', 'createdAt' => '2026-06-15T12:00:00+00:00', '_links' => [],
    ], 'refund')]);
    $payment = openMollieOrder()->payments()->sole();

    $gateway->refund($payment, 1070);

    expect($payment->refresh()->refunded_cents)->toBe(1070)->and($payment->status)->toBe(PaymentStatus::Refunded);
    $client->assertSent(CreatePaymentRefundRequest::class);
});

test('the gateway is available only with an API key', function () {
    expect(app(MollieGateway::class)->available())->toBeTrue();

    config(['shop.payments.mollie.api_key' => '']);

    expect(app(MollieGateway::class)->available())->toBeFalse();
});

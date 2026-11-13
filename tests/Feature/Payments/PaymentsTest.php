<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderConfirmation;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\BankTransferGateway;
use App\Payments\MollieGateway;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    config(['shop.seller.iban' => 'DE02120300000000202051', 'shop.payments.mollie.api_key' => null]);
    Storage::fake('local');
    Mail::fake();
});

function pendingBankOrder(array $attributes = []): Order
{
    $order = Order::factory()->create($attributes);
    $order->payments()->create([
        'gateway' => 'bank_transfer', 'gateway_id' => $order->number, 'status' => PaymentStatus::Pending,
        'amount_cents' => $order->total_cents, 'currency' => 'EUR',
    ]);

    return $order;
}

function paymentUrl(string $name, Order $order): string
{
    return route($name, ['locale' => 'de', 'order' => $order]);
}

test('only configured gateways are offered', function (?string $iban, ?string $mollieKey, array $expected) {
    config(['shop.seller.iban' => $iban, 'shop.payments.mollie.api_key' => $mollieKey]);

    expect(app(PaymentManager::class)->availableKeys())->toBe($expected);
})->with([
    'bank transfer only' => ['DE02120300000000202051', null, ['bank_transfer']],
    'mollie only' => ['', 'test_abc', ['mollie']],
    'both, in configured order' => ['DE02120300000000202051', 'test_abc', ['mollie', 'bank_transfer']],
    'neither' => ['', '', []],
]);

test('the manager resolves gateways by key and rejects unknown ones', function () {
    $manager = app(PaymentManager::class);

    expect($manager->keys())->toBe(['mollie', 'bank_transfer'])
        ->and($manager->gateway('bank_transfer'))->toBeInstanceOf(BankTransferGateway::class)
        ->and($manager->gateway('mollie'))->toBeInstanceOf(MollieGateway::class);

    $manager->gateway('paypal');
})->throws(InvalidArgumentException::class, 'Unknown payment gateway [paypal].');

test('starting a bank transfer records a pending payment and returns to the shop', function () {
    $order = Order::factory()->create(['total_cents' => 2390]);

    $url = app(BankTransferGateway::class)->start($order, 'https://shop.test/return', 'https://shop.test/hook');

    $payment = $order->payments()->sole();

    expect($url)->toBe('https://shop.test/return')
        ->and($payment->only(['gateway', 'gateway_id', 'amount_cents', 'currency']))->toBe([
            'gateway' => 'bank_transfer', 'gateway_id' => $order->number, 'amount_cents' => 2390, 'currency' => 'EUR',
        ])
        ->and($payment->status)->toBe(PaymentStatus::Pending);
});

test('confirming a bank transfer pays the payment and the order and issues the invoice', function () {
    $order = pendingBankOrder();
    $payment = $order->payments()->sole();

    app(BankTransferGateway::class)->confirm($payment);

    $order->refresh();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_gateway)->toBe('bank_transfer')
        ->and($order->invoices()->sole()->number)->toBe('RE-00001');
    Mail::assertSent(OrderConfirmation::class, 1);
});

test('the bank transfer gateway has no remote state', function () {
    $gateway = app(BankTransferGateway::class);
    $payment = pendingBankOrder()->payments()->sole();

    expect($gateway->sync($payment)->is($payment))->toBeTrue()
        ->and($gateway->handleWebhook(request()))->toBeNull()
        ->and($gateway->key())->toBe('bank_transfer');
});

test('bank transfers cannot be refunded through the gateway', function () {
    $payment = pendingBankOrder()->payments()->sole();

    app(BankTransferGateway::class)->refund($payment, 100);
})->throws(LogicException::class, 'refunds are made manually');

test('a webhook for an unknown gateway is a 404', function () {
    $this->post(route('webhooks.payments', 'paypal'), ['id' => 'tr_1'])->assertNotFound();
});

test('a webhook for an unknown payment id is acknowledged without side effects', function (string $gateway, array $payload) {
    config(['shop.payments.mollie.api_key' => 'test_abc']);
    $order = pendingBankOrder();

    $this->post(route('webhooks.payments', $gateway), $payload)->assertOk()->assertSee('OK');

    expect($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(Payment::query()->where('status', PaymentStatus::Paid)->count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'mollie with unknown id' => ['mollie', ['id' => 'tr_unknown']],
    'mollie without id' => ['mollie', []],
    'bank transfer' => ['bank_transfer', ['id' => 'whatever']],
]);

test('the payment return page sends a bank transfer customer to the confirmation', function () {
    $order = pendingBankOrder();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->get(paymentUrl('shop.payments.return', $order))
        ->assertRedirect(paymentUrl('shop.orders.complete', $order));
});

test('the payment return page sends a paid order to the confirmation', function () {
    $order = Order::factory()->paid()->create();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->get(paymentUrl('shop.payments.return', $order))
        ->assertRedirect(paymentUrl('shop.orders.complete', $order));
});

test('the payment return page offers a retry when there is no completed payment', function () {
    $order = Order::factory()->create();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->get(paymentUrl('shop.payments.return', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shop/payment-failed')
            ->where('order.uuid', $order->uuid)
            ->where('gateways', ['bank_transfer']));
});

test('the payment pages are closed to everyone but the owner, an admin and the placing session', function () {
    $order = Order::factory()->create();
    $stranger = User::factory()->create();

    $this->get(paymentUrl('shop.payments.return', $order))->assertForbidden();
    $this->post(paymentUrl('shop.payments.retry', $order), ['gateway' => 'bank_transfer'])->assertForbidden();
    $this->actingAs($stranger)->get(paymentUrl('shop.payments.return', $order))->assertForbidden();
    $this->withSession(['owned_orders' => ['another-uuid']])->get(paymentUrl('shop.payments.return', $order))->assertForbidden();
});

test('retrying a bank transfer reuses the pending payment of an open order', function () {
    $order = pendingBankOrder();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->post(paymentUrl('shop.payments.retry', $order), ['gateway' => 'bank_transfer'])
        ->assertRedirect(paymentUrl('shop.payments.return', $order));

    expect($order->payments()->count())->toBe(1);
});

test('retrying with an unavailable or missing gateway is rejected', function (array $payload, int $status) {
    $order = pendingBankOrder();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->postJson(paymentUrl('shop.payments.retry', $order), $payload)
        ->assertStatus($status);

    expect($order->payments()->count())->toBe(1);
})->with([
    'mollie is not configured' => [['gateway' => 'mollie'], 422],
    'unknown gateway' => [['gateway' => 'paypal'], 422],
    'gateway missing' => [[], 422],
]);

test('retrying a paid or cancelled order goes to the confirmation instead', function (callable $make) {
    $order = $make();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->post(paymentUrl('shop.payments.retry', $order), ['gateway' => 'bank_transfer'])
        ->assertRedirect(paymentUrl('shop.orders.complete', $order));

    expect($order->payments()->count())->toBe(0);
})->with([
    'paid' => [fn () => Order::factory()->paid()->create()],
    'cancelled' => [fn () => Order::factory()->create(['status' => OrderStatus::Cancelled])],
]);

test('the confirmation page shows the bank details of a pending transfer', function () {
    config(['shop.seller.bic' => 'BELADEBE', 'shop.seller.bank' => 'Berliner Sparkasse']);
    $order = pendingBankOrder();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->get(paymentUrl('shop.orders.complete', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shop/order-complete')
            ->where('bank.iban', 'DE02120300000000202051')
            ->where('bank.reference', $order->number)
            ->where('order.uuid', $order->uuid));
});

test('the confirmation page of a paid order shows no bank details', function () {
    $order = Order::factory()->paid()->create();

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->get(paymentUrl('shop.orders.complete', $order))
        ->assertInertia(fn (Assert $page) => $page->where('bank', null));
});

test('switching from a failed card payment to bank transfer starts a transfer', function () {
    $order = Order::factory()->create();
    $order->payments()->create(['gateway' => 'mollie', 'gateway_id' => 'tr_1', 'status' => PaymentStatus::Failed, 'amount_cents' => 1070, 'currency' => 'EUR']);

    $this->withSession(['owned_orders' => [$order->uuid]])
        ->post(paymentUrl('shop.payments.retry', $order), ['gateway' => 'bank_transfer'])
        ->assertRedirect(paymentUrl('shop.payments.return', $order));

    expect($order->payments()->latest('id')->first()->gateway)->toBe('bank_transfer');
});

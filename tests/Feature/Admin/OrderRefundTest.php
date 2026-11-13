<?php

use App\Datev\DatevExporter;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Invoicing\InvoiceIssuer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Payments\MollieGateway;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\CreatePaymentRefundRequest;
use Mollie\Api\MollieApiClient;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    Storage::fake('local');
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * A paid, invoiced order. Lines are [product, quantity, net, tax, discount, rate].
 *
 * @param  list<array{0: Product, 1: int, 2: int, 3: int, 4: int, 5: int}>  $lines
 * @param  array{0: int, 1: int}  $shipping  net and tax of the 7 % shipping charge
 * @param  array<string, mixed>  $attributes
 */
function invoicedOrder(array $lines, string $gateway = 'bank_transfer', array $shipping = [0, 0], array $attributes = []): Order
{
    $groups = [];
    $discount = 0;
    $order = Order::factory()->create(['status' => OrderStatus::Paid, 'paid_at' => now(), 'payment_gateway' => $gateway]);

    foreach ($lines as [$product, $quantity, $net, $tax, $lineDiscount, $rate]) {
        $order->items()->create([
            'product_id' => $product->id, 'name' => "Buch {$product->sku}", 'sku' => $product->sku, 'quantity' => $quantity,
            'unit_price_cents' => intdiv($net + $tax + $lineDiscount, $quantity), 'tax_class' => $rate === 7 ? 'reduced' : 'standard',
            'tax_rate' => $rate, 'tax_category' => 'S', 'discount_cents' => $lineDiscount,
            'line_net_cents' => $net, 'line_tax_cents' => $tax, 'line_gross_cents' => $net + $tax,
        ]);
        $groups[$rate] ??= ['rate' => (float) $rate, 'category' => 'S', 'net' => 0, 'tax' => 0, 'gross' => 0];
        $groups[$rate]['net'] += $net;
        $groups[$rate]['tax'] += $tax;
        $groups[$rate]['gross'] += $net + $tax;
        $discount += $lineDiscount;
    }

    [$shippingNet, $shippingTax] = $shipping;

    if ($shippingNet > 0) {
        $groups[7] ??= ['rate' => 7.0, 'category' => 'S', 'net' => 0, 'tax' => 0, 'gross' => 0];
        $groups[7]['net'] += $shippingNet;
        $groups[7]['tax'] += $shippingTax;
        $groups[7]['gross'] += $shippingNet + $shippingTax;
    }

    krsort($groups);

    $order->update([
        'subtotal_net_cents' => array_sum(array_column($groups, 'net')) - $shippingNet,
        'discount_cents' => $discount,
        'shipping_net_cents' => $shippingNet, 'shipping_tax_cents' => $shippingTax, 'shipping_tax_rate' => $shippingNet > 0 ? 7 : 0,
        'tax_cents' => array_sum(array_column($groups, 'tax')),
        'total_cents' => array_sum(array_column($groups, 'gross')),
        'tax_summary' => array_values($groups),
        ...$attributes,
    ]);

    $order->payments()->create([
        'gateway' => $gateway, 'gateway_id' => $gateway === 'mollie' ? 'tr_123' : $order->number, 'status' => PaymentStatus::Paid,
        'amount_cents' => $order->total_cents, 'currency' => 'EUR', 'paid_at' => now(),
    ]);
    app(InvoiceIssuer::class)->issueInvoice($order);

    return $order->refresh();
}

function refundItems(object $test, Order $order, array $quantities, array $flags = []): TestResponse
{
    $payload = ['quantities' => collect($quantities)->mapWithKeys(fn (int $quantity, int $index) => [$order->items[$index]->id => $quantity])->all(), ...$flags];

    return $test->post(route('admin.orders.refund', $order), $payload);
}

function creditNotes(Order $order): Collection
{
    return $order->invoices()->where('type', InvoiceType::CreditNote)->orderBy('id')->get();
}

function fakeMollieRefundOk(): MollieApiClient
{
    config(['shop.payments.mollie.api_key' => 'test_abc']);
    $client = MollieApiClient::fake([CreatePaymentRefundRequest::class => MockResponse::created([
        'resource' => 'refund', 'id' => 're_1', 'status' => 'queued', 'amount' => ['value' => '10.70', 'currency' => 'EUR'],
        'paymentId' => 'tr_123', 'createdAt' => '2026-06-15T12:00:00+00:00', '_links' => [],
    ], 'refund')], retainRequests: true);
    $gateway = app(MollieGateway::class);
    (new ReflectionProperty($gateway, 'client'))->setValue($gateway, $client);
    app()->instance(MollieGateway::class, $gateway);

    return $client;
}

test('refunding one of two different books credits just that line', function () {
    $a = Product::factory()->create(['stock' => 5]);
    $b = Product::factory()->create(['stock' => 5]);
    $order = invoicedOrder([[$a, 1, 1000, 70, 0, 7], [$b, 1, 1000, 190, 0, 19]]);

    refundItems($this, $order, [0, 1], ['restock' => true])->assertRedirect()->assertSessionHasNoErrors();

    $credit = creditNotes($order)->sole();
    $snapshot = $credit->snapshot;

    expect($credit->number)->toBe('GS-00001')
        ->and($credit->references->number)->toBe('RE-00001')
        ->and($credit->total_net_cents)->toBe(1000)
        ->and($credit->total_tax_cents)->toBe(190)
        ->and($credit->total_gross_cents)->toBe(1190)
        ->and($snapshot['type'])->toBe('credit_note')
        ->and($snapshot['lines'])->toHaveCount(1)
        ->and($snapshot['lines'][0]['sku'])->toBe($b->sku)
        ->and($snapshot['lines'][0]['quantity'])->toBe(1)
        ->and($snapshot['tax_groups'])->toEqual([['rate' => 19.0, 'category' => 'S', 'net' => 1000, 'tax' => 190, 'gross' => 1190]])
        ->and($snapshot['totals'])->toBe(['net' => 1000, 'tax' => 190, 'gross' => 1190])
        ->and($snapshot['shipping'])->toBeNull()
        ->and($order->items[1]->refresh()->refunded_quantity)->toBe(1)
        ->and($order->items[0]->refresh()->refunded_quantity)->toBe(0)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->cancelled_at)->toBeNull()
        ->and($b->refresh()->stock)->toBe(6)
        ->and($a->refresh()->stock)->toBe(5);
    Storage::disk('local')->assertExists($credit->pdf_path);
});

test('crediting a discounted line in two steps sums exactly to the invoice line', function () {
    // 3 x 10,00 gross, 1,00 coupon discount: 28,99 gross = 27,10 net + 1,90 tax, none of which divides by 3.
    $product = Product::factory()->create(['stock' => 0]);
    $order = invoicedOrder([[$product, 3, 2710, 190, 100, 7]], attributes: ['coupon_code' => 'SPRING']);

    refundItems($this, $order, [1])->assertSessionHasNoErrors();
    refundItems($this, $order, [2])->assertSessionHasNoErrors();

    [$first, $second] = creditNotes($order)->all();

    expect($first->snapshot['lines'][0])->toMatchArray(['quantity' => 1, 'net' => 903, 'tax' => 63, 'gross' => 966, 'discount_cents' => 33])
        ->and($first->snapshot['coupon']['discount_cents'])->toBe(33)
        ->and($second->snapshot['lines'][0])->toMatchArray(['quantity' => 2, 'net' => 1807, 'tax' => 127, 'gross' => 1934, 'discount_cents' => 67])
        ->and($first->total_net_cents + $second->total_net_cents)->toBe(2710)
        ->and($first->total_tax_cents + $second->total_tax_cents)->toBe(190)
        ->and($first->total_gross_cents + $second->total_gross_cents)->toBe(2900)
        ->and($second->number)->toBe('GS-00002')
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->cancelled_at)->not->toBeNull();
});

test('shipping is credited together with the last item and completes the refund', function () {
    $order = invoicedOrder([[Product::factory()->create(), 1, 1000, 70, 0, 7]], shipping: [458, 32]);

    refundItems($this, $order, [1], ['include_shipping' => '1'])->assertSessionHasNoErrors();

    $credit = creditNotes($order)->sole();

    expect($credit->total_gross_cents)->toBe(1560)
        ->and($credit->snapshot['shipping'])->toMatchArray(['net' => 458, 'tax' => 32, 'gross' => 490])
        ->and($credit->snapshot['tax_groups'])->toEqual([['rate' => 7.0, 'category' => 'S', 'net' => 1458, 'tax' => 102, 'gross' => 1560]])
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded);
});

test('shipping alone can be credited and only once; the items finish the refund', function () {
    $order = invoicedOrder([[Product::factory()->create(), 1, 1000, 70, 0, 7]], shipping: [458, 32]);

    $this->post(route('admin.orders.refund', $order), ['include_shipping' => '1'])->assertSessionHasNoErrors();

    $credit = creditNotes($order)->sole();

    expect($credit->snapshot['lines'])->toBe([])
        ->and($credit->total_gross_cents)->toBe(490)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid);

    refundItems($this, $order, [1], ['include_shipping' => '1'])->assertSessionHasErrors('include_shipping');
    refundItems($this, $order, [1])->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe(OrderStatus::Refunded)->and(creditNotes($order)->last()->total_gross_cents)->toBe(1070);
});

test('stock stays untouched unless the books go back on the shelf', function () {
    $product = Product::factory()->create(['stock' => 2]);
    $order = invoicedOrder([[$product, 3, 3000, 210, 0, 7]]);

    refundItems($this, $order, [1], ['restock' => '0'])->assertSessionHasNoErrors();
    expect($product->refresh()->stock)->toBe(2);

    refundItems($this, $order, [2], ['restock' => '1'])->assertSessionHasNoErrors();
    expect($product->refresh()->stock)->toBe(4);
});

test('a Mollie order is refunded the credited gross amount only', function () {
    $client = fakeMollieRefundOk();
    $a = Product::factory()->create();
    $b = Product::factory()->create();
    $order = invoicedOrder([[$a, 1, 1000, 70, 0, 7], [$b, 1, 1000, 190, 0, 19]], 'mollie');

    refundItems($this, $order, [1, 0])->assertSessionHasNoErrors();

    $payment = $order->payments()->sole();

    expect($payment->refunded_cents)->toBe(1070)
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid);
    $client->assertSentCount(1);

    refundItems($this, $order, [0, 1])->assertSessionHasNoErrors();

    expect($payment->refresh()->refunded_cents)->toBe(2260)
        ->and($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded);
    $client->assertSentCount(2);
});

test('a failed gateway refund keeps the credit note and reports the error', function () {
    Exceptions::fake();
    config(['shop.payments.mollie.api_key' => 'test_abc']);
    $client = MollieApiClient::fake([CreatePaymentRefundRequest::class => MockResponse::error(500, 'Server Error', 'Mollie is down')]);
    $gateway = app(MollieGateway::class);
    (new ReflectionProperty($gateway, 'client'))->setValue($gateway, $client);
    app()->instance(MollieGateway::class, $gateway);
    $order = invoicedOrder([[Product::factory()->create(), 2, 2000, 140, 0, 7]], 'mollie');

    refundItems($this, $order, [1])->assertRedirect();

    expect(creditNotes($order))->toHaveCount(1)->and($order->payments()->sole()->refunded_cents)->toBe(0);
    Exceptions::assertReportedCount(1);
});

test('a bank transfer is not refunded through a gateway', function () {
    $order = invoicedOrder([[Product::factory()->create(), 2, 2000, 140, 0, 7]]);

    refundItems($this, $order, [1])->assertRedirect()->assertInertiaFlash('toast.message', 'Credit note GS-00001 issued. Refund the bank transfer manually.');

    expect($order->payments()->sole()->refunded_cents)->toBe(0);
});

test('invalid refunds are rejected without side effects', function (array $quantitiesByIndex, array $flags) {
    $product = Product::factory()->create(['stock' => 1]);
    $order = invoicedOrder([[$product, 2, 2000, 140, 0, 7]]);
    refundItems($this, $order, [1])->assertSessionHasNoErrors();

    $payload = ['quantities' => collect($quantitiesByIndex)->mapWithKeys(fn ($q, $i) => [$i === 'foreign' ? 999999 : $order->items[$i]->id => $q])->all(), ...$flags];
    $this->post(route('admin.orders.refund', $order), $payload)->assertSessionHasErrors();

    expect(creditNotes($order))->toHaveCount(1)
        ->and($order->items[0]->refresh()->refunded_quantity)->toBe(1)
        ->and($product->refresh()->stock)->toBe(1);
})->with([
    'more than refundable' => [[2], ['restock' => '1']],
    'empty selection' => [[0], ['restock' => '1']],
    'foreign item id' => [['foreign' => 1], []],
    'negative quantity' => [[-1], []],
    'no quantities' => [[], []],
]);

test('orders that are not paid cannot be partially refunded', function (OrderStatus $status) {
    $order = invoicedOrder([[Product::factory()->create(), 1, 1000, 70, 0, 7]]);
    $order->update(['status' => $status, 'paid_at' => $status === OrderStatus::Pending ? null : $order->paid_at]);

    refundItems($this, $order, [1])->assertSessionHasErrors('order');

    expect(creditNotes($order))->toHaveCount(0);
})->with([OrderStatus::Pending, OrderStatus::Cancelled, OrderStatus::Refunded]);

test('a shipped order can be partially refunded', function () {
    $order = invoicedOrder([[Product::factory()->create(), 2, 2000, 140, 0, 7]]);
    $order->update(['status' => OrderStatus::Shipped, 'shipped_at' => now()]);

    refundItems($this, $order, [1])->assertSessionHasNoErrors();

    expect(creditNotes($order))->toHaveCount(1)->and($order->refresh()->status)->toBe(OrderStatus::Shipped);
});

test('a partial credit note exports to DATEV as a Haben booking on the matching account', function () {
    config(['shop.datev.debtor_account' => '10000', 'shop.datev.accounts' => ['domestic_19' => '4400', 'domestic_7' => '4300']]);
    $order = invoicedOrder([[Product::factory()->create(), 1, 1000, 70, 0, 7], [Product::factory()->create(), 1, 1000, 190, 0, 19]]);

    refundItems($this, $order, [0, 1])->assertSessionHasNoErrors();

    $bookings = app(DatevExporter::class)->bookingsFor(creditNotes($order)->sole());

    expect($bookings)->toHaveCount(1)
        ->and($bookings[0]['Umsatz (ohne Soll/Haben-Kz)'])->toBe(11.9)
        ->and($bookings[0]['Soll/Haben-Kennzeichen'])->toBe('H')
        ->and($bookings[0]['Konto'])->toBe('10000')
        ->and($bookings[0]['Gegenkonto (ohne BU-Schlüssel)'])->toBe('4400')
        ->and($bookings[0]['Belegfeld 1'])->toBe('GS-00001');
});

test('cancelling after a partial refund credits and restocks only the rest', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $order = invoicedOrder([[$product, 3, 2710, 190, 100, 7]], shipping: [458, 32]);

    refundItems($this, $order, [1], ['restock' => '1'])->assertSessionHasNoErrors();
    $this->post(route('admin.orders.cancel', $order))->assertRedirect();

    [$partial, $rest] = creditNotes($order)->all();

    expect($rest->snapshot['lines'][0])->toMatchArray(['quantity' => 2, 'net' => 1807, 'tax' => 127, 'gross' => 1934])
        ->and($rest->snapshot['shipping']['gross'])->toBe(490)
        ->and($partial->total_gross_cents + $rest->total_gross_cents)->toBe($order->total_cents)
        ->and($product->refresh()->stock)->toBe(3)
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->items[0]->refunded_quantity)->toBe(3);
});

test('cancelling a fully credited order again is rejected and issues nothing more', function () {
    $order = invoicedOrder([[Product::factory()->create(), 1, 1000, 70, 0, 7]]);
    refundItems($this, $order, [1])->assertSessionHasNoErrors();

    $this->post(route('admin.orders.cancel', $order))->assertStatus(422);

    expect(creditNotes($order))->toHaveCount(1);
});

test('the full credit note stays idempotent', function () {
    $order = invoicedOrder([[Product::factory()->create(), 2, 2000, 140, 0, 7]]);
    $issuer = app(InvoiceIssuer::class);

    $first = $issuer->issueCreditNote($order);

    expect($issuer->issueCreditNote($order)->is($first))->toBeTrue()
        ->and(Invoice::query()->where('type', InvoiceType::CreditNote)->count())->toBe(1)
        ->and(OrderItem::query()->sole()->refunded_quantity)->toBe(2);
});

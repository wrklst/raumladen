<?php

use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\DatevExport;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    Mail::fake();
});

/**
 * Every admin endpoint as [method, route name, parameter factory].
 */
function adminEndpoints(): array
{
    return [
        'dashboard' => ['get', 'admin.dashboard', fn () => []],
        'products index' => ['get', 'admin.products.index', fn () => []],
        'products store' => ['post', 'admin.products.store', fn () => []],
        'products update' => ['put', 'admin.products.update', fn () => [Product::factory()->create()]],
        'products destroy' => ['delete', 'admin.products.destroy', fn () => [Product::factory()->create()]],
        'orders index' => ['get', 'admin.orders.index', fn () => []],
        'orders show' => ['get', 'admin.orders.show', fn () => [Order::factory()->create()]],
        'orders ship' => ['post', 'admin.orders.ship', fn () => [Order::factory()->paid()->create()]],
        'orders cancel' => ['post', 'admin.orders.cancel', fn () => [Order::factory()->create()]],
        'orders confirm payment' => ['post', 'admin.orders.confirm-payment', fn () => [Order::factory()->create()]],
        'customers index' => ['get', 'admin.customers.index', fn () => []],
        'customers update' => ['patch', 'admin.customers.update', fn () => [User::factory()->create()]],
        'coupons store' => ['post', 'admin.coupons.store', fn () => []],
        'coupons destroy' => ['delete', 'admin.coupons.destroy', fn () => [Coupon::factory()->create()]],
        'customer groups store' => ['post', 'admin.customer-groups.store', fn () => []],
        'customer groups destroy' => ['delete', 'admin.customer-groups.destroy', fn () => [CustomerGroup::factory()->create()]],
        'invoices index' => ['get', 'admin.invoices.index', fn () => []],
        'datev index' => ['get', 'admin.datev.index', fn () => []],
        'datev store' => ['post', 'admin.datev.store', fn () => []],
        'datev download' => ['get', 'admin.datev.download', fn () => [DatevExport::query()->create(['from_date' => now(), 'to_date' => now(), 'invoice_count' => 0, 'booking_count' => 0, 'path' => 'datev/x.csv', 'sha256' => 'x'])]],
        'pages store' => ['post', 'admin.pages.store', fn () => []],
        'pages destroy' => ['delete', 'admin.pages.destroy', fn () => [Page::factory()->create()]],
    ];
}

test('guests are sent to the login page', function (string $method, string $route, Closure $parameters) {
    $this->{$method}(route($route, $parameters()))->assertRedirect(route('login'));
})->with(adminEndpoints());

test('customers are forbidden and nothing changes', function (string $method, string $route, Closure $parameters) {
    $url = route($route, $parameters());
    $this->actingAs(User::factory()->create());
    $before = [Product::withTrashed()->count(), Order::query()->count(), Coupon::query()->count(), Page::query()->count(), CustomerGroup::query()->count()];

    $this->{$method}($url)->assertForbidden();

    expect([Product::withTrashed()->count(), Order::query()->count(), Coupon::query()->count(), Page::query()->count(), CustomerGroup::query()->count()])->toBe($before);
    Mail::assertNothingSent();
})->with(adminEndpoints());

test('an admin with an unverified email is asked to verify first', function () {
    $this->actingAs(User::factory()->admin()->unverified()->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('invoice downloads are admin-only', function () {
    $order = Order::factory()->paid()->create();
    $invoice = Invoice::query()->create([
        'type' => 'invoice', 'number' => 'RE-00001', 'order_id' => $order->id, 'issued_at' => now(), 'currency' => 'EUR',
        'total_net_cents' => 1, 'total_tax_cents' => 0, 'total_gross_cents' => 1, 'snapshot' => [],
    ]);

    $this->get(route('admin.invoices.download', $invoice))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.invoices.download', $invoice))->assertForbidden();
});

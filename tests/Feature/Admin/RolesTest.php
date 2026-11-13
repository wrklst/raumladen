<?php

use App\Enums\UserRole;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
});

function staff(UserRole $role): User
{
    return User::factory()->create(['role' => $role]);
}

test('each role reaches its own areas of the backend and no others', function (UserRole $role, array $allowed) {
    $pages = [
        'admin.dashboard', 'admin.orders.index', 'admin.products.index', 'admin.pages.index', 'admin.coupons.index',
        'admin.customers.index', 'admin.customer-groups.index', 'admin.invoices.index', 'admin.datev.index',
        'admin.settings.edit', 'admin.staff.index',
    ];

    $this->actingAs(staff($role));

    foreach ($pages as $page) {
        $response = $this->get(route($page));

        in_array($page, $allowed, true) ? $response->assertOk() : $response->assertForbidden();
    }
})->with([
    'administrator' => [UserRole::Admin, [
        'admin.dashboard', 'admin.orders.index', 'admin.products.index', 'admin.pages.index', 'admin.coupons.index',
        'admin.customers.index', 'admin.customer-groups.index', 'admin.invoices.index', 'admin.datev.index',
        'admin.settings.edit', 'admin.staff.index',
    ]],
    'shop manager' => [UserRole::Manager, [
        'admin.dashboard', 'admin.orders.index', 'admin.products.index', 'admin.pages.index', 'admin.coupons.index',
        'admin.customers.index', 'admin.customer-groups.index', 'admin.invoices.index',
    ]],
    'fulfilment' => [UserRole::Fulfilment, ['admin.dashboard', 'admin.orders.index']],
    'editor' => [UserRole::Editor, ['admin.dashboard', 'admin.products.index', 'admin.pages.index']],
    'accounting' => [UserRole::Accounting, ['admin.dashboard', 'admin.orders.index', 'admin.invoices.index', 'admin.datev.index']],
    'customer' => [UserRole::Customer, []],
]);

test('shipping and payment actions on an order are separate permissions', function () {
    $paid = fn () => Order::factory()->create(['status' => 'paid', 'paid_at' => now()]);

    // Fulfilment ships but cannot cancel or refund.
    $this->actingAs(staff(UserRole::Fulfilment));
    $this->post(route('admin.orders.cancel', $paid()))->assertForbidden();
    $this->post(route('admin.orders.refund', $paid()))->assertForbidden();
    $this->post(route('admin.orders.confirm-payment', Order::factory()->create()))->assertForbidden();
    $this->post(route('admin.orders.ship', $paid()))->assertRedirect();

    // Accounting handles money but does not ship.
    $this->actingAs(staff(UserRole::Accounting));
    $this->post(route('admin.orders.ship', $paid()))->assertForbidden();
    $this->post(route('admin.orders.cancel', Order::factory()->create()))->assertRedirect();

    // The editor does not see orders at all.
    $this->actingAs(staff(UserRole::Editor));
    $this->get(route('admin.orders.show', $paid()))->assertForbidden();
});

test('the backend tells the page which areas to show', function () {
    $this->actingAs(staff(UserRole::Editor))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.areas', ['catalog', 'content'])
            ->where('auth.user.is_staff', true));
});

test('every backend user manages their own profile and security, customers do not', function () {
    $this->actingAs(staff(UserRole::Fulfilment))->get(route('profile.edit'))->assertOk();
    $this->actingAs(staff(UserRole::Customer))->get(route('profile.edit'))->assertForbidden();
});

test('backend users of any role sign in with their password, not with a mailed code', function (UserRole $role) {
    $user = staff($role);

    $this->post(route('shop.account.login.code', ['locale' => 'en']), ['email' => $user->email])
        ->assertSessionHasErrors('email');
})->with([UserRole::Admin, UserRole::Manager, UserRole::Fulfilment, UserRole::Editor, UserRole::Accounting]);

test('a backend user lands in the backend, a customer in their account', function () {
    $this->actingAs(staff(UserRole::Accounting))->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    $this->actingAs(staff(UserRole::Customer))->get(route('dashboard'))->assertRedirect();
    expect($this->actingAs(staff(UserRole::Customer))->get(route('dashboard'))->headers->get('Location'))->toContain('/account');
});

test('the dashboard shows only the figures of the role', function () {
    $this->actingAs(staff(UserRole::Editor))->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue_month_cents', null)
            ->where('stats.orders_month', null)
            ->where('stats.low_stock', 0)
            ->where('recent_orders', null));

    $this->actingAs(staff(UserRole::Fulfilment))->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue_month_cents', null)
            ->where('stats.orders_to_ship', 0)
            ->where('stats.low_stock', null)
            ->has('recent_orders'));
});

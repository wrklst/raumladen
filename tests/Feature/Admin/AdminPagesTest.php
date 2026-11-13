<?php

use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Page;
use App\Models\User;

test('admin pages render', function (string $route) {
    CustomerGroup::factory()->consumer()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route($route))
        ->assertOk();
})->with([
    'admin.dashboard', 'admin.products.index', 'admin.products.create', 'admin.orders.index',
    'admin.customers.index', 'admin.customer-groups.index', 'admin.coupons.index', 'admin.coupons.create',
    'admin.invoices.index', 'admin.datev.index', 'admin.pages.index', 'admin.pages.create',
]);

test('admin edit pages render', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.coupons.edit', Coupon::factory()->create()))->assertOk();
    $this->get(route('admin.pages.edit', Page::factory()->create()))->assertOk();
});

test('customers cannot reach admin pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.datev.index'))
        ->assertForbidden();
});

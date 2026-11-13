<?php

use App\Models\CustomerGroup;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('admins are sent to the admin dashboard', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('admin.dashboard'));
});

test('customers are sent to their orders', function () {
    CustomerGroup::factory()->consumer()->create();
    $user = User::factory()->create(['locale' => 'de']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('shop.account.orders.index', ['locale' => 'de']));
});

test('customers cannot open the admin area', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.dashboard'))->assertForbidden();
});

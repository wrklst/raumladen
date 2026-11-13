<?php

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;

function canViewOrder(?User $user, Order $order): bool
{
    return app(OrderPolicy::class)->view($user, $order);
}

test('the owner can view their order', function () {
    $order = Order::factory()->create();

    expect(canViewOrder($order->user, $order))->toBeTrue();
});

test('an admin can view any order', function () {
    expect(canViewOrder(User::factory()->admin()->create(), Order::factory()->create()))->toBeTrue();
});

test('another customer cannot view the order', function () {
    expect(canViewOrder(User::factory()->create(), Order::factory()->create()))->toBeFalse();
});

test('a guest can view only orders placed in their session', function () {
    $order = Order::factory()->create();

    expect(canViewOrder(null, $order))->toBeFalse();

    session()->put(OrderPolicy::SESSION_KEY, ['some-other-uuid']);
    expect(canViewOrder(null, $order))->toBeFalse();

    session()->put(OrderPolicy::SESSION_KEY, ['some-other-uuid', $order->uuid]);
    expect(canViewOrder(null, $order))->toBeTrue();
});

test('the session key also grants a signed-in stranger access to the order they placed as a guest', function () {
    $order = Order::factory()->create();
    session()->put(OrderPolicy::SESSION_KEY, [$order->uuid]);

    expect(canViewOrder(User::factory()->create(), $order))->toBeTrue();
});

test('the policy is enforced on the order confirmation page', function () {
    $order = Order::factory()->create();
    $route = route('shop.orders.complete', ['locale' => 'de', 'order' => $order]);

    $this->get($route)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($route)->assertForbidden();
    $this->actingAs($order->user)->get($route)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($route)->assertOk();
});

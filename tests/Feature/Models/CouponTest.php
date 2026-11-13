<?php

use App\Models\Coupon;
use App\Models\CustomerGroup;

test('a valid coupon has no rejection reason', function () {
    expect(Coupon::factory()->make()->rejectionReason(1000, null))->toBeNull();
});

test('rejectionReason names why a coupon cannot be used', function (array $state, string $reason) {
    $group = CustomerGroup::factory()->create();
    $coupon = Coupon::factory()->make([...$state, 'customer_group_id' => ($state['customer_group_id'] ?? null) === 'other' ? $group->id + 1 : null]);

    expect($coupon->rejectionReason(1000, $group))->toBe($reason);
})->with([
    'inactive' => [['is_active' => false], 'inactive'],
    'not started' => [['starts_at' => now()->addDay()], 'not_started'],
    'expired' => [['expires_at' => now()->subMinute()], 'expired'],
    'exhausted' => [['max_uses' => 3, 'uses' => 3], 'exhausted'],
    'wrong group' => [['customer_group_id' => 'other'], 'wrong_group'],
    'below minimum' => [['min_order_cents' => 1001], 'below_minimum'],
]);

test('the minimum order value is inclusive', function () {
    expect(Coupon::factory()->make(['min_order_cents' => 1000])->rejectionReason(1000, null))->toBeNull();
});

test('a group-bound coupon is valid for that group only', function () {
    $group = CustomerGroup::factory()->create();
    $coupon = Coupon::factory()->make(['customer_group_id' => $group->id]);

    expect($coupon->rejectionReason(1000, $group))->toBeNull()
        ->and($coupon->rejectionReason(1000, null))->toBe('wrong_group');
});

test('a coupon with uses left or a future expiry is accepted', function () {
    $coupon = Coupon::factory()->make(['max_uses' => 3, 'uses' => 2, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);

    expect($coupon->rejectionReason(1000, null))->toBeNull();
});

test('discountFor computes percent and fixed discounts capped at the subtotal', function (Coupon $coupon, int $subtotal, int $expected) {
    expect($coupon->discountFor($subtotal))->toBe($expected);
})->with([
    'percent' => [fn () => Coupon::factory()->make(['value' => 10]), 2500, 250],
    'percent rounds' => [fn () => Coupon::factory()->make(['value' => 15]), 333, 50],
    'fixed' => [fn () => Coupon::factory()->fixed(500)->make(), 2500, 500],
    'fixed is capped at the subtotal' => [fn () => Coupon::factory()->fixed(5000)->make(), 2500, 2500],
    '100 percent' => [fn () => Coupon::factory()->make(['value' => 100]), 2500, 2500],
]);

test('findByCode ignores case and surrounding whitespace', function () {
    $coupon = Coupon::factory()->create(['code' => 'SPRING10']);

    expect(Coupon::findByCode('  spring10 ')?->is($coupon))->toBeTrue()
        ->and(Coupon::findByCode('nope'))->toBeNull();
});

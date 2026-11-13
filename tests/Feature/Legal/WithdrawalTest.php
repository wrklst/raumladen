<?php

use App\Mail\WithdrawalReceived;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    config(['shop.seller.email' => 'shop@space-issues.test']);
    Mail::fake();
});

test('the withdrawal form is public and takes the order number from the link', function () {
    $this->get(route('shop.withdrawal.create', ['locale' => 'de', 'order' => 'B-00042']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shop/withdrawal')
            ->where('defaults.order_number', 'B-00042')
            ->where('received_at', null));
});

test('a withdrawal is stored with its time and acknowledged to the consumer and the seller', function () {
    $this->freezeTime();
    $order = Order::factory()->create(['number' => 'B-00042', 'email' => 'kundin@example.com']);

    $this->post(route('shop.withdrawal.store', ['locale' => 'de']), [
        'order_number' => 'B-00042',
        'name' => 'Erika Mustermann',
        'email' => 'kundin@example.com',
        'statement' => 'Nur das zweite Buch.',
    ])->assertRedirect(route('shop.withdrawal.create', ['locale' => 'de']));

    $withdrawal = Withdrawal::query()->sole();

    expect($withdrawal)
        ->order_id->toBe($order->id)
        ->statement->toBe('Nur das zweite Buch.')
        ->locale->toBe('de')
        ->and($withdrawal->created_at->toDateTimeString())->toBe(now()->toDateTimeString());

    Mail::assertSent(WithdrawalReceived::class, fn (WithdrawalReceived $mail) => $mail->hasTo('kundin@example.com'));
    Mail::assertSent(WithdrawalReceived::class, fn (WithdrawalReceived $mail) => $mail->hasTo('shop@space-issues.test'));

    // The page then shows the acknowledgement instead of the form.
    $this->get(route('shop.withdrawal.create', ['locale' => 'de']))
        ->assertInertia(fn (Assert $page) => $page->where('received_at', Withdrawal::query()->sole()->created_at->toIso8601String()));
});

test('a withdrawal counts even when the order cannot be matched, and the answer is the same', function () {
    Order::factory()->create(['number' => 'B-00042', 'email' => 'someone-else@example.com']);

    $this->post(route('shop.withdrawal.store', ['locale' => 'en']), [
        'order_number' => 'B-00042',
        'name' => 'Erika Mustermann',
        'email' => 'kundin@example.com',
    ])->assertRedirect(route('shop.withdrawal.create', ['locale' => 'en']));

    expect(Withdrawal::query()->sole()->order_id)->toBeNull();
    Mail::assertSent(WithdrawalReceived::class, 2);
});

test('the acknowledgement states what was declared and when', function () {
    $this->travelTo('2026-11-13 14:05:00', function () {
        $withdrawal = Withdrawal::factory()->create(['order_number' => 'B-00042', 'statement' => 'Alles.', 'locale' => 'de']);

        app()->setLocale('de');

        (new WithdrawalReceived($withdrawal))
            ->assertHasSubject('Ihr Widerruf zur Bestellung B-00042 ist eingegangen')
            ->assertSeeInText('B-00042')
            ->assertSeeInText('Alles.')
            ->assertSeeInText('13.11.2026')
            ->assertSeeInText('15:05 (Europe/Berlin)');
    });
});

test('an incomplete withdrawal is rejected and nothing is sent', function () {
    $this->post(route('shop.withdrawal.store', ['locale' => 'de']), ['email' => 'not-an-email'])
        ->assertSessionHasErrors(['order_number', 'name', 'email']);

    expect(Withdrawal::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

<?php

use App\Enums\UserRole;
use App\Mail\LoginCode;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->consumer = CustomerGroup::factory()->consumer()->create();
    Mail::fake();
});

function loginUrl(string $name = 'shop.account.login', string $locale = 'de'): string
{
    return route($name, ['locale' => $locale]);
}

/**
 * Requests a code and returns the one that was mailed.
 */
function requestLoginCode(object $test, string $email = 'kunde@example.com', string $locale = 'de'): string
{
    $test->post(loginUrl('shop.account.login.code', $locale), ['email' => $email])->assertSessionHasNoErrors();

    $code = null;
    Mail::assertSent(LoginCode::class, function (LoginCode $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

test('the login page is shown to guests and skipped by signed-in customers', function () {
    $this->get(loginUrl())->assertOk()->assertInertia(fn (Assert $page) => $page->component('account/login'));

    $this->actingAs(User::factory()->create())
        ->get(loginUrl())
        ->assertRedirect(route('shop.account.orders.index', ['locale' => 'de']));
});

test('a code is mailed to the lower-cased address and remembered in the session', function () {
    $code = requestLoginCode($this, 'Kunde@Example.COM');

    expect($code)->toMatch('/^\d{6}$/');
    Mail::assertSent(LoginCode::class, fn (LoginCode $mail) => $mail->hasTo('kunde@example.com'));
    $this->assertSame('kunde@example.com', session('login.email'));
});

test('the code mail follows the storefront locale', function () {
    requestLoginCode($this, locale: 'en');

    Mail::assertSent(LoginCode::class, fn (LoginCode $mail) => $mail->locale === 'en');
});

test('requesting a code validates the email', function (string $email) {
    $this->post(loginUrl('shop.account.login.code'), ['email' => $email])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
})->with(['', 'not-an-email']);

test('administrators are refused a code and pointed to the password login', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post(loginUrl('shop.account.login.code'), ['email' => 'Admin@Example.com'])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
    $this->assertNull(session('login.email'));
});

test('the right code signs a new customer in and creates the account', function () {
    $code = requestLoginCode($this, 'neu@example.com');

    $this->post(loginUrl('shop.account.login.verify'), ['code' => $code])
        ->assertRedirect(route('shop.account.orders.index', ['locale' => 'de']));

    $user = User::query()->where('email', 'neu@example.com')->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->name)->toBe('neu')
        ->and($user->customer_group_id)->toBe($this->consumer->id)
        ->and($user->locale)->toBe('de')
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertNull(session('login.email'));
});

test('the right code signs an existing customer in without creating a second account', function () {
    $user = User::factory()->unverified()->create(['email' => 'kunde@example.com', 'name' => 'Erika']);
    $code = requestLoginCode($this);

    $this->post(loginUrl('shop.account.login.verify'), ['code' => $code])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1)
        ->and($user->refresh()->name)->toBe('Erika')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('the customer is sent on to the page they wanted', function () {
    $code = requestLoginCode($this);

    $this->withSession(['url.intended' => route('shop.account.profile.edit', ['locale' => 'de'])])
        ->post(loginUrl('shop.account.login.verify'), ['code' => $code])
        ->assertRedirect(route('shop.account.profile.edit', ['locale' => 'de']));
});

test('a code works only once', function () {
    $code = requestLoginCode($this);

    $this->post(loginUrl('shop.account.login.verify'), ['code' => $code])->assertSessionHasNoErrors();
    $this->post(route('logout'));
    $this->withSession(['login.email' => 'kunde@example.com'])
        ->post(loginUrl('shop.account.login.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a wrong code is rejected and nothing is created', function (string $code) {
    requestLoginCode($this);

    $this->post(loginUrl('shop.account.login.verify'), ['code' => $code])->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
})->with([
    'well-formed but wrong' => ['000000'],
    'too short' => ['123'],
    'too long' => ['1234567'],
    'not digits' => ['abcdef'],
    'empty' => [''],
]);

test('an expired code is rejected', function () {
    $code = requestLoginCode($this);

    $this->travel(11)->minutes();

    $this->post(loginUrl('shop.account.login.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('a code is still valid just before it expires', function () {
    $code = requestLoginCode($this);

    $this->travel(9)->minutes();

    $this->post(loginUrl('shop.account.login.verify'), ['code' => $code])->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('a code cannot be verified without having requested one in this session', function () {
    $code = requestLoginCode($this);
    $this->flushSession();

    $this->post(loginUrl('shop.account.login.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('requesting and verifying codes is throttled', function (string $route, array $payload) {
    foreach (range(1, 6) as $attempt) {
        $this->post(loginUrl($route), $payload)->assertStatus(302);
    }

    $this->post(loginUrl($route), $payload)->assertStatus(429);
})->with([
    'request' => ['shop.account.login.code', ['email' => 'kunde@example.com']],
    'verify' => ['shop.account.login.verify', ['code' => '000000']],
]);

test('both code routes carry the throttle middleware', function (string $name) {
    expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('throttle:6,1');
})->with(['shop.account.login.code', 'shop.account.login.verify']);

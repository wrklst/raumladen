<?php

use App\Enums\UserRole;
use App\Mail\StaffInvitation;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    Mail::fake();
    $this->admin = User::factory()->admin()->create();
});

function invitationUrl(User $user): string
{
    return URL::temporarySignedRoute('invitation.show', now()->addDays(7), ['user' => $user]);
}

test('there is no public registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertNotFound();

    expect(User::query()->where('email', 'x@example.com')->exists())->toBeFalse();
});

test('an administrator invites a person with a role', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), ['name' => 'Jonas Weber', 'email' => 'Jonas@Example.com', 'role' => 'editor'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'jonas@example.com')->sole();

    expect($user->role)->toBe(UserRole::Editor)
        ->and($user->password)->toBeNull();

    Mail::assertSent(StaffInvitation::class, fn (StaffInvitation $mail) => $mail->hasTo('jonas@example.com') && $mail->user->is($user));
});

test('the invitation mail carries a signed link that expires', function () {
    $user = User::factory()->create(['role' => UserRole::Editor, 'password' => null, 'name' => 'Jonas']);

    $mail = new StaffInvitation($user, $this->admin);

    $mail->assertSeeInHtml('/invitation/'.$user->id, false)
        ->assertSeeInHtml('signature=', false)
        ->assertSeeInText($this->admin->name)
        ->assertSeeInText('Editor');
});

test('only an administrator may invite, and a customer role or a taken address is refused', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))
        ->post(route('admin.staff.store'), ['name' => 'A', 'email' => 'a@example.com', 'role' => 'editor'])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), ['name' => 'A', 'email' => 'a@example.com', 'role' => 'customer'])
        ->assertSessionHasErrors('role');
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), ['name' => 'A', 'email' => $this->admin->email, 'role' => 'editor'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('the invited person sets a password through the link and is signed in', function () {
    $user = User::factory()->unverified()->create(['role' => UserRole::Fulfilment, 'password' => null]);
    $url = invitationUrl($user);
    auth()->logout();

    $this->get($url)->assertOk();
    $this->post($url, ['password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->password)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    // The link works once.
    auth()->logout();
    $this->get($url)->assertStatus(410);
});

test('a tampered or expired invitation link is refused', function () {
    $user = User::factory()->create(['role' => UserRole::Editor, 'password' => null]);
    $other = User::factory()->create(['role' => UserRole::Editor, 'password' => null]);
    $url = invitationUrl($user);

    $this->get(str_replace('/invitation/'.$user->id, '/invitation/'.$other->id, $url))->assertForbidden();

    $this->travel(8)->days();

    $this->get($url)->assertForbidden();
    $this->post($url, ['password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery'])->assertForbidden();

    expect($user->refresh()->password)->toBeNull();
});

test('an invitation cannot be used to take over a customer account', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer, 'password' => null]);

    $this->get(invitationUrl($customer))->assertStatus(410);
});

test('an invitation can be sent again while it is pending', function () {
    $pending = User::factory()->create(['role' => UserRole::Editor, 'password' => null]);
    $active = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($this->admin)->post(route('admin.staff.invite', $pending))->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.staff.invite', $active))->assertNotFound();

    Mail::assertSent(StaffInvitation::class, 1);
});

test('roles can be changed, but the last administrator stays one', function () {
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($this->admin)->patch(route('admin.staff.update', $editor), ['role' => 'accounting'])->assertSessionHasNoErrors();

    expect($editor->refresh()->role)->toBe(UserRole::Accounting);

    $this->actingAs($this->admin)->patch(route('admin.staff.update', $this->admin), ['role' => 'editor'])->assertSessionHasErrors('role');

    expect($this->admin->refresh()->role)->toBe(UserRole::Admin);

    // An invited administrator who has not accepted yet does not count as a second one.
    User::factory()->create(['role' => UserRole::Admin, 'password' => null]);

    $this->actingAs($this->admin)->patch(route('admin.staff.update', $this->admin), ['role' => 'editor'])->assertSessionHasErrors('role');
});

test('removing backend access deletes the user, unless they have orders as a customer', function () {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $buyer = User::factory()->create(['role' => UserRole::Manager]);
    Order::factory()->create(['user_id' => $buyer->id]);

    $this->actingAs($this->admin)->delete(route('admin.staff.destroy', $editor))->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->delete(route('admin.staff.destroy', $buyer))->assertSessionHasNoErrors();

    expect(User::query()->find($editor->id))->toBeNull()
        ->and($buyer->refresh()->role)->toBe(UserRole::Customer)
        ->and($buyer->password)->toBeNull();

    $this->actingAs($this->admin)->delete(route('admin.staff.destroy', $this->admin))->assertSessionHasErrors('staff');

    expect($this->admin->refresh()->exists)->toBeTrue();
});

<?php

use App\Enums\UserRole;
use App\Mail\StaffInvitation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/*
 * The backend in a real browser. Needs the built assets: `npm run build`.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['app.name' => 'Space Issues']);
    Mail::fake();
});

test('the login page shows the shop name, offers no registration and switches language', function () {
    visit('/login')
        ->assertSee('Space Issues')
        ->assertDontSee('Sign up')
        ->assertNoSmoke()
        ->click('Deutsch')
        ->assertSee('Anmelden')
        ->assertNoSmoke();
});

test('an administrator sees the whole backend, grouped by topic, in German', function () {
    $this->actingAs(User::factory()->admin()->create(['locale' => 'de']));

    visit('/admin')
        ->assertSee('Verkauf')
        ->assertSee('Katalog')
        ->assertSee('Buchhaltung')
        ->assertSee('Verwaltung')
        ->assertSee('Shop-Einstellungen')
        ->assertNoSmoke();
});

test('an editor sees only books and pages in the menu', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor, 'locale' => 'de']));

    visit('/admin')
        ->assertSee('Katalog')
        ->assertSee('Produkte')
        ->assertDontSee('Bestellungen')
        ->assertDontSee('Buchhaltung')
        ->assertDontSee('Shop-Einstellungen')
        ->assertNoSmoke();
});

test('settings are saved from the backend', function () {
    $this->actingAs(User::factory()->admin()->create(['locale' => 'de']));

    visit('/admin/settings')
        ->assertSee('Vor dem Livegang')
        ->assertSee('Kanäle und Suchmaschinen')
        ->assertNoSmoke()
        ->fill('seller_address1', 'Weydingerstraße 14')
        ->press('#seller button[type=submit]')
        ->assertSee('Verkäufer gespeichert.');

    expect(config('shop.seller.address1'))->toBe('Weydingerstraße 14');
});

test('a person is invited, sets a password through the link and lands in the backend', function () {
    $this->actingAs(User::factory()->admin()->create(['locale' => 'de']));

    visit('/admin/staff')
        ->fill('name', 'Ludwig Engel')
        ->fill('email', 'ludwig@example.com')
        ->select('#role', 'editor')
        ->press('Einladung senden')
        ->assertSee('Einladung offen')
        ->assertNoSmoke();

    $invited = User::query()->where('email', 'ludwig@example.com')->sole();
    Mail::assertSent(StaffInvitation::class);

    auth()->logout();

    visit(URL::temporarySignedRoute('invitation.show', now()->addDays(7), ['user' => $invited]))
        ->assertSee('Ludwig Engel')
        ->fill('password', 'correct horse battery')
        ->fill('password_confirmation', 'correct horse battery')
        ->press('[type=submit]')
        ->assertPathIs('/admin')
        ->assertNoSmoke();

    expect($invited->refresh()->password)->not->toBeNull();
});

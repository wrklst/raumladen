<?php

use App\Models\CustomerGroup;
use App\Models\Page;
use Database\Seeders\DatabaseSeeder;

test('every response carries the baseline security headers', function () {
    CustomerGroup::factory()->consumer()->create();

    $this->get('/de')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/de')->assertHeader('Strict-Transport-Security');
});

test('the legal texts follow the shop settings and mark what is not set', function () {
    config([
        'shop.seller.name' => 'Beispielverlag e.V.',
        'shop.seller.vat_id' => '',
        'shop.processor.name' => 'Hosting Beispiel GmbH',
        'shop.processor.address' => '',
    ]);

    $this->seed(DatabaseSeeder::class);

    $imprint = Page::query()->where('slug', 'imprint')->sole();
    $privacy = Page::query()->where('slug', 'privacy')->sole();
    $withdrawal = Page::query()->where('slug', 'withdrawal')->sole();

    expect($imprint->renderedBody('de'))
        ->toContain("<strong>Beispielverlag e.V.</strong><br>\n")
        ->toContain('[TODO: USt-IdNr.]', 'Hosting Beispiel GmbH, [TODO: Anschrift]')
        ->and($imprint->renderedBody('en'))->toContain('[TODO: VAT ID]')
        ->and($privacy->renderedBody('de'))->toContain('Hosting Beispiel GmbH', 'Mollie B.V.', 'Photon', 'Auftragsverarbeit')
        ->and($withdrawal->renderedBody('de'))->toContain('Muster-Widerrufsformular', '„Vertrag widerrufen“');

    // Changing a setting changes the published text, without editing the page.
    config(['shop.seller.vat_id' => 'DE123456789', 'shop.shipping.1.delivery_days' => [4, 8]]);

    expect($imprint->renderedBody('de'))->toContain('DE123456789')->not->toContain('[TODO: USt-IdNr.]')
        ->and(Page::query()->where('slug', 'shipping')->sole()->renderedBody('de'))->toContain('4–8 Werktage');

    // No placeholder is left unresolved in any text.
    foreach (Page::query()->get() as $page) {
        foreach (['de', 'en'] as $locale) {
            expect(preg_match('/(?<![\w(>]):[a-z_]{3,}\b/', strip_tags($page->renderedBody($locale))))->toBe(0, $page->slug.' '.$locale);
        }
    }
});

test('shop:check lists what blocks going live and passes when nothing does', function () {
    $this->seed(DatabaseSeeder::class);

    $this->artisan('shop:check')
        ->expectsOutputToContain('the name is still the example')
        ->expectsOutputToContain('invoices would be invalid')
        ->expectsOutputToContain('Represented by is not set')
        ->assertFailed();

    Page::query()->delete();
    config([
        'app.debug' => false, 'app.url' => 'https://raumladen.test', 'mail.default' => 'smtp',
        'shop.seller' => ['name' => 'Musterverlag e.V.', 'address1' => 'Weg 1', 'zip' => '10178', 'city' => 'Berlin', 'country' => 'DE', 'email' => 'shop@raumladen.test', 'phone' => '030 1', 'vat_id' => 'DE123456789', 'tax_number' => '', 'register' => 'VR 1 B', 'representatives' => 'A, B', 'iban' => 'DE02', 'bic' => 'BYLA', 'bank' => 'Bank'],
        'shop.processor' => ['name' => 'Hosting Beispiel GmbH', 'address' => 'Weg 2, Berlin', 'location' => 'Deutschland', 'log_retention' => '14 Tagen'],
        'shop.carrier' => 'Deutsche Post',
        'shop.shipping' => array_map(fn (array $zone) => [...$zone, 'delivery_days' => [3, 9]], config('shop.shipping')),
        'shop.payments.mollie.api_key' => 'live_abc',
    ]);
    $this->seed(DatabaseSeeder::class);

    $this->artisan('shop:check')->expectsOutputToContain('Nothing missing')->assertSuccessful();
});

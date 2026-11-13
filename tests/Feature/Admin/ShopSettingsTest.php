<?php

use App\Enums\UserRole;
use App\Invoicing\InvoiceIssuer;
use App\Mail\OrderShipped;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Settings\Appearance;
use App\Settings\ShopSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    CustomerGroup::factory()->consumer()->create();
    $this->admin = User::factory()->admin()->create();
});

test('seller details saved in the backend replace the config values', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'seller'), [
            'seller_name' => 'Beispielverlag e.V.', 'seller_address1' => 'Weydingerstraße 14', 'seller_zip' => '10178',
            'seller_city' => 'Berlin', 'seller_email' => 'shop@raumladen.test', 'seller_vat_id' => 'DE123456789',
            'seller_iban' => 'DE02120300000000202051',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(config('shop.seller.address1'))->toBe('Weydingerstraße 14')
        ->and(config('shop.seller.vat_id'))->toBe('DE123456789')
        // An IBAN makes prepayment available at checkout.
        ->and(app(PaymentManager::class)->availableKeys())->toContain('bank_transfer');

    // A fresh boot of the application reads them from the database again.
    config(['shop.seller.address1' => 'from the config file']);
    ShopSettings::apply();

    expect(config('shop.seller.address1'))->toBe('Weydingerstraße 14');
});

test('the mollie key is stored encrypted, switches online payment on and is never sent to the browser', function () {
    config(['shop.payments.mollie.api_key' => null]);

    expect(app(PaymentManager::class)->availableKeys())->not->toContain('mollie');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'payment'), ['mollie_key' => 'live_AbC123', 'invoice_due_days' => 14, 'unpaid_cancel_days' => 10])
        ->assertSessionHasNoErrors();

    expect(config('shop.payments.mollie.api_key'))->toBe('live_AbC123')
        ->and(config('shop.orders.unpaid_cancel_days'))->toBe(10)
        ->and(Setting::query()->find('shop.payments.mollie.api_key')->value)->not->toContain('live_AbC123')
        ->and(app(PaymentManager::class)->availableKeys())->toContain('mollie');

    $response = $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk();

    expect($response->getContent())->not->toContain('live_AbC123');
    $response->assertInertia(fn (Assert $page) => $page->where('groups.1.key', 'payment')->where('groups.1.values.mollie_key', true));

    // Saving the form again with the key field left empty keeps the key.
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'payment'), ['mollie_key' => '', 'invoice_due_days' => 14, 'unpaid_cancel_days' => 10])
        ->assertSessionHasNoErrors();

    expect(config('shop.payments.mollie.api_key'))->toBe('live_AbC123');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'payment'), ['mollie_key' => 'not a key', 'invoice_due_days' => 14, 'unpaid_cancel_days' => 10])
        ->assertSessionHasErrors('mollie_key');
});

test('mail settings are applied and can be tried with a test message', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'mail'), [
            'mail_mailer' => 'smtp', 'mail_host' => 'mail.example.test', 'mail_port' => 587, 'mail_scheme' => '',
            'mail_username' => 'shop', 'mail_password' => 's3cret', 'mail_from_address' => 'shop@raumladen.test', 'mail_from_name' => 'Raumladen',
        ])
        ->assertSessionHasNoErrors();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('mail.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.password'))->toBe('s3cret')
        ->and(config('mail.from.address'))->toBe('shop@raumladen.test')
        ->and(Setting::query()->find('mail.mailers.smtp.password')->value)->not->toContain('s3cret');

    Mail::fake();

    $this->actingAs($this->admin)->post(route('admin.settings.test-mail'))->assertSessionHasNoErrors();

    Mail::shouldReceive('raw')->andThrow(new RuntimeException('Connection refused'));

    $this->actingAs($this->admin)->post(route('admin.settings.test-mail'))->assertSessionHasErrors('mail_test');
});

test('shipping prices are entered in euro and delivery times need both ends', function () {
    $input = [
        'shipping_de_price' => '4.50', 'shipping_de_free_from' => '', 'shipping_de_days_min' => 1, 'shipping_de_days_max' => 3,
        'shipping_eu_price' => '9.90', 'shipping_eu_free_from' => '100', 'shipping_eu_days_min' => '', 'shipping_eu_days_max' => '',
        'shipping_world_price' => '19.90', 'shipping_world_free_from' => '', 'shipping_world_days_min' => '', 'shipping_world_days_max' => '',
        'carrier' => 'Deutsche Post',
    ];

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'shipping'), $input)->assertSessionHasNoErrors();

    expect(config('shop.shipping.0'))->toMatchArray(['code' => 'DE', 'price_cents' => 450, 'free_from_cents' => null, 'delivery_days' => [1, 3]])
        ->and(config('shop.shipping.1'))->toMatchArray(['price_cents' => 990, 'free_from_cents' => 10000])
        ->and(config('shop.shipping.1'))->not->toHaveKey('delivery_days')
        ->and(config('shop.carrier'))->toBe('Deutsche Post');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'shipping'), [...$input, 'shipping_eu_days_min' => 4])
        ->assertSessionHasErrors('shipping_eu_days_max');
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'shipping'), [...$input, 'shipping_de_days_min' => 5, 'shipping_de_days_max' => 2])
        ->assertSessionHasErrors('shipping_de_days_max');
});

test('tax switches are saved as real booleans', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'tax'), ['tax_small_business' => '0', 'tax_oss_registered' => '1'])
        ->assertSessionHasNoErrors();

    expect(config('shop.tax.oss_registered'))->toBeTrue()
        ->and(config('shop.tax.small_business'))->toBeFalse();
});

test('the settings page lists what still blocks going live', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings')
            ->has('groups', 9)
            ->where('problems', fn ($problems) => collect($problems)->contains(fn (string $problem) => str_contains($problem, 'invoices would be invalid'))));
});

test('only administrators change settings, and unknown groups do not exist', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))
        ->put(route('admin.settings.update', 'seller'), ['seller_name' => 'Hijacked'])
        ->assertForbidden();

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'nonsense'), [])->assertNotFound();

    expect(Setting::query()->count())->toBe(0);
});

test('the DATEV numbers and accounts are edited in the backend and used by the export', function () {
    $accounts = [
        'datev_consultant_number' => '12345', 'datev_client_number' => '678', 'datev_skr' => '03', 'datev_debtor_account' => '10000',
        'datev_account_19' => '8400', 'datev_account_7' => '8300', 'datev_account_0' => '8200', 'datev_account_ic' => '8125',
        'datev_account_export' => '8120', 'datev_account_oss' => '8320', 'datev_account_rc' => '8336', 'datev_account_small' => '8195',
    ];

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'datev'), $accounts)->assertSessionHasNoErrors();

    expect(config('shop.datev.accounts.domestic_7'))->toBe('8300')
        ->and(config('shop.datev.skr'))->toBe('03')
        ->and(config('shop.datev.consultant_number'))->toBe('12345');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'datev'), [...$accounts, 'datev_account_7' => 'abc'])
        ->assertSessionHasErrors('datev_account_7');

    // The DATEV page itself no longer dumps the configuration.
    $this->actingAs($this->admin)->get(route('admin.datev.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('settings'));
});

test('feeds for Google and Meta are switched on and off in the backend', function () {
    Product::factory()->create(['title' => ['de' => 'Raum', 'en' => 'Space']]);

    // Defaults: Google on, Meta off.
    $this->get('/feeds/google-de.xml')->assertOk()->assertSee('Raum');
    $this->get('/feeds/meta-de.xml')->assertNotFound();

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'channels'), [
            'site_description_de' => 'Ein Beispielladen für Bücher.', 'seo_indexable' => '1', 'seo_llms_txt' => '1', 'google_feed' => '0', 'meta_feed' => '1',
            'google_verification' => 'abc_123-XYZ', 'meta_verification' => 'meta456',
            'same_as' => "https://example.org/about\n\n https://instagram.com/example \n",
        ])
        ->assertSessionHasNoErrors();

    $this->get('/feeds/google-de.xml')->assertNotFound();
    $this->get('/feeds/meta-de.xml')->assertOk()->assertSee('Raum');
    $this->get('/feeds/other-de.xml')->assertNotFound();

    $html = $this->get('/de')->getContent();

    expect($html)
        ->toContain('<meta name="google-site-verification" content="abc_123-XYZ">')
        ->toContain('<meta name="facebook-domain-verification" content="meta456">')
        ->and(config('shop.site.same_as'))->toBe(['https://example.org/about', 'https://instagram.com/example'])
        // The shop's own words reach search engines and AI assistants.
        ->and($html)->toContain('<meta name="description" content="Ein Beispielladen für Bücher.">')
        ->and($this->get('/llms.txt')->getContent())->toContain('> Ein Beispielladen für Bücher.')
        ->and($html)->toContain('instagram.com/example');

    // A verification code is pasted into the page head: only plain codes are accepted.
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'channels'), ['google_verification' => '"><script>'])
        ->assertSessionHasErrors('google_verification');
});

test('a shop that is not ready keeps search engines and AI crawlers out', function () {
    expect($this->get('/de')->getContent())->toContain('content="index, follow');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'channels'), ['seo_indexable' => '0', 'seo_llms_txt' => '1', 'google_feed' => '1', 'meta_feed' => '0'])
        ->assertSessionHasNoErrors();

    expect($this->get('/robots.txt')->getContent())->toBe("User-agent: *\nDisallow: /\n")
        ->and($this->get('/de')->getContent())->toContain('<meta name="robots" content="noindex, nofollow">');
    $this->get('/llms.txt')->assertNotFound();

    // The feed address in the settings is the one that works.
    $this->actingAs($this->admin)->get(route('admin.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('groups', fn ($groups) => str_contains(
            collect(collect($groups)->firstWhere('key', 'channels')['fields'])->firstWhere('name', 'google_feed')['help'],
            url('/feeds/google-de.xml'),
        )));
});

test('logo, invoice note and mail signature are set in the backend and used by invoice and emails', function () {
    Storage::fake('public');
    Storage::fake('local');
    $logo = UploadedFile::fake()->image('logo.png', 300, 100);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'appearance'), [
            'logo' => $logo,
            'invoice_note' => "Vielen Dank.\nRaumladen",
            'mail_signature' => "Herzlich\nRaumladen",
        ])
        ->assertSessionHasNoErrors();

    $path = config('shop.appearance.logo');

    Storage::disk('public')->assertExists($path);

    expect(Appearance::logoUrl())->toContain($path)
        ->and(Appearance::logoDataUri())->toStartWith('data:image/png;base64,')
        ->and(Appearance::mailSignature())->toBe("Herzlich\nRaumladen");

    $order = Order::factory()->create(['status' => 'paid', 'paid_at' => now()]);
    $invoice = app(InvoiceIssuer::class)->issueInvoice($order);
    $html = view('invoices.pdf', ['invoice' => $invoice, 's' => $invoice->snapshot])->render();

    expect($html)->toContain('data:image/png;base64,')->toContain("Vielen Dank.<br />\nRaumladen");

    (new OrderShipped($order))
        ->assertSeeInHtml($path, false)
        ->assertSeeInOrderInText(['Herzlich', 'Raumladen']);

    // Saving again without a file keeps the logo; the remove tick deletes it.
    $this->actingAs($this->admin)->put(route('admin.settings.update', 'appearance'), ['invoice_note' => ''])->assertSessionHasNoErrors();

    expect(config('shop.appearance.logo'))->toBe($path);

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'appearance'), ['logo_remove' => '1'])->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($path);

    expect(Appearance::logoUrl())->toBeNull()
        // Without a signature the seller signs.
        ->and(Appearance::mailSignature())->toBe(config('shop.seller.name'));

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'appearance'), ['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])
        ->assertSessionHasErrors('logo');
});

test('the shop can bring its own icon for the browser tab and home screens', function () {
    Storage::fake('public');

    expect($this->get('/de')->getContent())->toContain('href="/favicon.svg"');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'appearance'), ['icon' => UploadedFile::fake()->image('icon.png', 200, 100)])
        ->assertSessionHasErrors('icon');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'appearance'), ['icon' => UploadedFile::fake()->image('icon.png', 512, 512)])
        ->assertSessionHasNoErrors();

    $html = $this->get('/de')->getContent();

    expect($html)->toContain('<link rel="apple-touch-icon" href="'.Appearance::iconUrl().'">')
        ->not->toContain('href="/favicon.svg"');
});

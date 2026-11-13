<?php

use App\Invoicing\InvoiceIssuer;
use App\Mail\OrderShipped;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Theme\Theme;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A shop checkout usually has a theme installed and selected in its
 * environment. These tests start from the built-in theme, whatever is installed.
 */
beforeEach(function () {
    config(['shop.theme' => null, 'mail.markdown.paths' => [resource_path('views/vendor/mail')]]);
    app()->forgetInstance(Theme::class);
    app()->forgetInstance(Markdown::class);
    View::getFinder()->setPaths([resource_path('views')]);
    View::getFinder()->flush();
});

/**
 * Boot the application's theme from the fixture in tests/Fixtures/theme, the
 * way a theme package is picked up on a real boot.
 */
function useFixtureTheme(): Theme
{
    config(['shop.theme' => 'tests/Fixtures/theme']);
    app()->forgetInstance(Theme::class);
    Theme::current()->register();

    return Theme::current();
}

test('without SHOP_THEME the built-in theme is used', function () {
    CustomerGroup::factory()->consumer()->create();

    expect(Theme::current()->path)->toBe(resource_path('themes/default'))
        ->and(Theme::current()->cssClass())->toBe('theme-default')
        ->and(Theme::current()->provides('no-motion'))->toBeFalse();

    $this->get('/de')
        ->assertSee('<html lang="de" class="theme-default">', false)
        ->assertInertia(fn (Assert $page) => $page->where('theme', ['class' => 'theme-default', 'high_contrast' => false, 'no_motion' => false]));
});

test('a theme is found as a package, as a path, and a missing one is named', function () {
    expect(Theme::resolvePath('tests/Fixtures/theme'))->toBe(base_path('tests/Fixtures/theme'))
        ->and(Theme::resolvePath(base_path('tests/Fixtures/theme').'/'))->toBe(base_path('tests/Fixtures/theme'))
        // A package name is looked up in vendor/ first.
        ->and(Theme::resolvePath('laravel/framework'))->toBe(base_path('vendor/laravel/framework'))
        ->and(fn () => Theme::resolvePath('acme/not-installed'))->toThrow(RuntimeException::class, 'acme/not-installed');
});

test('the theme names its class and what it already provides, for the page and the display options', function () {
    CustomerGroup::factory()->consumer()->create();
    $theme = useFixtureTheme();

    expect($theme->name())->toBe('Test Theme')
        ->and($theme->cssClass())->toBe('theme-test-theme');

    $this->get('/de')
        ->assertSee('<html lang="de" class="theme-test-theme">', false)
        ->assertInertia(fn (Assert $page) => $page->where('theme', ['class' => 'theme-test-theme', 'high_contrast' => false, 'no_motion' => true]));
});

test('the backend keeps its own look whatever the theme', function () {
    useFixtureTheme();

    // The class is on <html> for storefront pages only.
    expect($this->get('/login')->getContent())->toContain('<html lang="en" class="">');
});

test('a theme replaces the invoice template', function () {
    CustomerGroup::factory()->consumer()->create();
    Storage::fake('local');
    $order = Order::factory()->create(['status' => 'paid', 'paid_at' => now()]);

    // The shop's own template first.
    expect(view('invoices.pdf', ['invoice' => null, 's' => ['number' => 'RE-1']])->getPath())->toBe(resource_path('views/invoices/pdf.blade.php'));

    useFixtureTheme();

    expect(view('invoices.pdf')->getPath())->toBe(base_path('tests/Fixtures/theme/resources/views/invoices/pdf.blade.php'));

    $invoice = app(InvoiceIssuer::class)->issueInvoice($order);

    expect(Storage::disk('local')->get($invoice->pdf_path))->toStartWith('%PDF');
});

test('a theme restyles the emails and leaves templates it does not bring untouched', function () {
    CustomerGroup::factory()->consumer()->create();
    $order = Order::factory()->create();

    expect((new OrderShipped($order))->render())->not->toContain('#000000');

    useFixtureTheme();

    $html = (new OrderShipped($order))->render();

    // The theme's mail CSS is inlined; the mail's own text still comes from the shop's template.
    expect($html)->toContain('#000000')->toContain($order->number);
});

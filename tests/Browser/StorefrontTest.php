<?php

use App\Address\AddressSuggester;
use App\Enums\TaxTreatment;
use App\Mail\WithdrawalReceived;
use App\Models\Order;
use App\Models\Withdrawal;
use App\Theme\Theme;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
 * Real-browser tests of the storefront (Playwright through Pest). They need
 * the built assets: `npm run build`.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['shop.seller.iban' => 'DE02120300000000202051', 'app.name' => 'Raumladen']);
    Storage::fake('local');
    Mail::fake();
    Http::fake([AddressSuggester::ENDPOINT.'*' => Http::response(['features' => [
        ['properties' => ['type' => 'house', 'housenumber' => '157', 'street' => 'Hauptstraße', 'city' => 'Bergisch Gladbach', 'postcode' => '51465', 'countrycode' => 'DE']],
    ]])]);
});

const ISSUE = '/de/products/die-kunst-des-langsamen-lesens';

dataset('storefront pages', ['/de', ISSUE, '/en/products/the-art-of-slow-reading', '/de/pages/imprint', '/de/pages/accessibility', '/de/cart', '/de/account/login', '/de/withdraw']);

test('pages load without console errors or accessibility issues', function (string $path) {
    visit($path)->assertNoSmoke()->assertNoAccessibilityIssues();
})->with('storefront pages');

test('pages have no accessibility issues in dark mode', function (string $path) {
    visit($path)->inDarkMode()
        ->assertScript('document.documentElement.classList.contains("dark")')
        ->assertNoAccessibilityIssues();
})->with('storefront pages');

test('cart and checkout with a book in them have no accessibility issues', function () {
    visit(ISSUE)
        ->press('In den Warenkorb')
        ->assertPathIs('/de/cart')
        ->assertNoAccessibilityIssues()
        ->click('Kasse')
        ->assertPathIs('/de/checkout')
        ->assertSee('Zahlungspflichtig bestellen')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

test('nothing scrolls sideways on a 320px wide screen', function (string $path) {
    visit($path)->resize(320, 640)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['/de', ISSUE, '/de/cart', '/de/pages/imprint']);

test('the first tab stop is a skip link that moves focus into the content', function () {
    visit('/de')
        ->keys('[class~="font-sans"]', 'Tab')
        ->assertScript('document.activeElement.textContent', 'Zum Inhalt springen')
        ->keys('[href="#main"]', 'Enter')
        ->assertScript('location.hash', '#main');
});

test('following a link moves focus to the new page content', function () {
    visit('/de')
        ->click('Die Kunst des langsamen Lesens')
        ->assertPathIs(ISSUE)
        ->assertScript('document.activeElement.id', 'main')
        ->assertTitle('Die Kunst des langsamen Lesens – Raumladen');
});

test('the colour scheme can be switched and survives a reload', function () {
    visit('/de')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->press('Anzeige')
        ->assertSee('Anzeige und Barrierefreiheit')
        ->click('Dunkel')
        ->assertScript('document.documentElement.classList.contains("dark")')
        // A theme may fade its colours; judge the contrast once they have arrived.
        ->wait(0.7)
        ->assertNoAccessibilityIssues()
        ->refresh()
        // Rendered dark by the server from the cookie: no flash of the light theme.
        ->assertSourceHas('class="dark '.Theme::current()->cssClass().'"');
});

test('the accessibility tools change the page and survive a reload', function () {
    visit('/de')
        ->press('Anzeige')
        ->click('150 %')
        ->click('Links unterstreichen')
        ->assertScript('getComputedStyle(document.documentElement).fontSize', '24px')
        ->assertScript('getComputedStyle(document.querySelector("footer a")).textDecorationLine', 'underline')
        ->refresh()
        ->assertScript('document.documentElement.dataset.a11yText', '2xl')
        ->assertScript('getComputedStyle(document.documentElement).fontSize', '24px')
        // Reflow at 150 % text on a small screen (WCAG 1.4.10).
        ->resize(320, 640)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
});

test('everything the storefront loads comes from the shop itself', function () {
    visit('/de')
        ->assertScript('performance.getEntriesByType("resource").filter((entry) => entry.name.startsWith("http")).every((entry) => new URL(entry.name).host === location.host)');
});

test('a visitor orders a book with a suggested address in as few steps as possible', function () {
    visit(ISSUE)
        ->press('In den Warenkorb')
        ->click('Kasse')
        ->type('email', 'kundin@example.com')
        ->type('[name="billing_address[first_name]"]', 'Erika')
        ->type('[name="billing_address[last_name]"]', 'Mustermann')
        ->typeSlowly('[name="billing_address[address1]"]', 'Hauptstr', 20)
        ->assertSeeIn('[role="listbox"]', 'Hauptstraße 157, 51465 Bergisch Gladbach')
        ->click('[role="option"]')
        ->assertValue('[name="billing_address[zip]"]', '51465')
        ->assertValue('[name="billing_address[city]"]', 'Bergisch Gladbach')
        ->radio('gateway', 'bank_transfer')
        ->press('Zahlungspflichtig bestellen')
        ->assertSee('Vielen Dank für Ihre Bestellung')
        ->assertSee('DE02120300000000202051')
        ->assertNoAccessibilityIssues();

    $order = Order::query()->sole();

    expect($order->email)->toBe('kundin@example.com')
        ->and($order->shipping_address)->toMatchArray(['address1' => 'Hauptstraße 157', 'zip' => '51465', 'city' => 'Bergisch Gladbach', 'country' => 'DE'])
        ->and($order->tax_treatment)->toBe(TaxTreatment::Domestic)
        ->and($order->total_cents)->toBe(2400 + 390);
});

test('an incomplete order puts focus on the first invalid field and says why', function () {
    $page = visit(ISSUE)->press('In den Warenkorb')->click('Kasse')->assertPathIs('/de/checkout');

    // Skip the browser's own required-field check to reach the server's.
    $page->script('document.querySelector("main form").noValidate = true');

    $page->press('Zahlungspflichtig bestellen')
        ->assertScript('document.activeElement.name', 'email')
        ->assertScript('document.activeElement.getAttribute("aria-invalid")', 'true')
        ->assertScript('document.getElementById(document.activeElement.getAttribute("aria-describedby")).textContent.length > 0')
        ->assertNoAccessibilityIssues();

    expect(Order::query()->count())->toBe(0);
});

test('the dark catalogue has no light block where a row of the grid is not full', function () {
    visit('/de')->inDarkMode()
        ->assertScript('getComputedStyle(document.querySelector("main ul")).backgroundColor', 'rgba(0, 0, 0, 0)');
});

test('the header stays in place while the page scrolls', function () {
    $page = visit('/de/pages/privacy')->resize(900, 500);

    $page->script('window.scrollTo(0, 800)');

    $page->assertScript('window.scrollY > 0')
        ->assertScript('document.querySelector("header").getBoundingClientRect().top', 0);
});

test('the search in the header finds an issue', function () {
    visit('/de/pages/imprint')
        ->type('q', 'kunst')
        ->keys('[name="q"]', 'Enter')
        ->assertPathIs('/de')
        ->assertQueryStringHas('q', 'kunst')
        ->assertSee('Suche: kunst')
        ->assertSee('Treffer: 1')
        ->assertSee('Die Kunst des langsamen Lesens')
        ->assertDontSee('Typografie für Vereine')
        ->assertNoAccessibilityIssues();
});

test('clicking an author lists all issues by that author', function () {
    visit(ISSUE)
        ->click('Lena Hartmann')
        ->assertPathIs('/de')
        ->assertQueryStringHas('author', 'Lena Hartmann')
        ->assertSeeIn('#main h1', 'Lena Hartmann')
        ->assertSee('Treffer: 1')
        ->assertNoAccessibilityIssues();
});

test('a contract can be withdrawn from the link in the footer', function () {
    visit('/de')
        ->click('Vertrag widerrufen')
        ->assertPathIs('/de/withdraw')
        ->type('order_number', 'B-00001')
        ->type('name', 'Erika Mustermann')
        ->type('email', 'kundin@example.com')
        ->press('Widerruf bestätigen')
        ->assertSee('Wir haben Ihren Widerruf erhalten')
        ->assertNoAccessibilityIssues();

    expect(Withdrawal::query()->sole()->order_number)->toBe('B-00001');
    Mail::assertSent(WithdrawalReceived::class, 2);
});

test('the quantity field and the add-to-cart button line up', function () {
    visit(ISSUE)->assertScript('(() => { const field = document.getElementById("quantity").getBoundingClientRect(); const button = document.querySelector("main form button[type=submit]").getBoundingClientRect(); return field.top === button.top && field.bottom === button.bottom; })()');
});

test('results appear while typing in the search, and the caret stays in the field', function () {
    // A catalogue too large for the browser index: the server answers.
    config(['shop.search.browser_index_max' => 0]);

    visit('/de/pages/imprint')
        ->typeSlowly('q', 'kunst', 30)
        ->assertSee('Treffer: 1')
        ->assertPathIs('/de')
        ->assertQueryStringHas('q', 'kunst')
        ->assertScript('document.activeElement.id', 'site-search')
        ->assertValue('q', 'kunst')
        ->assertDontSee('Typografie für Vereine')
        ->assertNoSmoke();
});

test('with a small catalogue the search answers from the browser, without a request per keystroke', function () {
    visit('/de/pages/imprint')
        ->typeSlowly('q', 'kunst', 30)
        ->assertSee('Treffer: 1')
        ->assertPathIs('/de')
        ->assertQueryStringHas('q', 'kunst')
        ->assertScript('document.activeElement.id', 'site-search')
        ->assertTitle('Suche: kunst – Raumladen')
        // One download of the index, no search request to the server.
        ->assertScript('performance.getEntriesByType("resource").filter((entry) => entry.name.includes("/search-index")).length', 1)
        ->assertScript('performance.getEntriesByType("resource").filter((entry) => entry.name.includes("q=")).length', 0)
        ->clear('q')
        ->assertSee('Typografie für Vereine')
        ->assertNoSmoke();
});

test('the cart shows each issue with its cover', function () {
    visit(ISSUE)
        ->press('In den Warenkorb')
        ->assertPathIs('/de/cart')
        // A text-only cover in portrait format, set in type like on the catalogue.
        ->assertScript('(() => { const cover = document.querySelector("main li [aria-hidden=true]"); const box = cover.getBoundingClientRect(); return cover.textContent === "Die Kunst des langsamen Lesens" && Math.round(box.height / box.width * 3) === 4; })()')
        ->assertNoAccessibilityIssues();
});

test('a visit is reported in one batch, without a cookie and without anything stored in the browser', function () {
    $page = visit('/de')
        ->click('Die Kunst des langsamen Lesens')
        ->assertPathIs(ISSUE);

    // Leaving the tab sends the batch.
    $page->script('Object.defineProperty(document, "visibilityState", { value: "hidden", configurable: true }); document.dispatchEvent(new Event("visibilitychange"));');
    $page->wait(0.5)
        // Only what the shop needs to work: the session and its form protection. No statistics cookie, no storage.
        ->assertScript('document.cookie.split("; ").filter(Boolean).every((cookie) => /^(XSRF-TOKEN|[a-z_]*session)=/i.test(cookie))')
        ->assertScript('localStorage.length', 0)
        ->assertScript('sessionStorage.length', 0);

    // One batch for the whole visit. (That the batch is stored anonymously is tested in
    // tests/Feature/Analytics; this test browser announces itself as headless and is filtered out as a bot.)
    $page->assertScript('performance.getEntriesByType("resource").filter((entry) => entry.name.endsWith("/site-events")).length', 1);
});

test('contrast and motion are offered unless the theme already provides them', function () {
    $theme = Theme::current();
    $page = visit('/de')->press('Anzeige');

    if ($theme->provides('high-contrast')) {
        $page->assertDontSee('Höherer Kontrast');
    } else {
        $page->click('Höherer Kontrast')->assertScript('document.documentElement.dataset.a11yContrast', 'more');
    }

    if ($theme->provides('no-motion')) {
        $page->assertDontSee('Bewegung reduzieren');
    } else {
        $page->click('Bewegung reduzieren');
    }

    // Either way nothing fades or slides now: by the theme, or by the visitor's choice.
    $page->assertScript('parseFloat(getComputedStyle(document.querySelector("[data-slot=sheet-content]")).transitionDuration) < 0.001');
});

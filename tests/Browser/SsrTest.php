<?php

use Database\Seeders\DatabaseSeeder;
use Symfony\Component\Process\Process;

/*
 * Server-side rendering end to end: Laravel asks the Node SSR server for the
 * markup, the browser hydrates it. Needs `npm run build:ssr`; skipped
 * without the bundle.
 */
beforeAll(function () {
    if (! file_exists(__DIR__.'/../../bootstrap/ssr/app.js')) {
        return;
    }

    $GLOBALS['ssr'] = new Process(['node', 'bootstrap/ssr/app.js'], dirname(__DIR__, 2));
    $GLOBALS['ssr']->start();
    $GLOBALS['ssr']->waitUntil(fn (string $type, string $output) => str_contains($output, 'started'));
});

afterAll(fn () => ($GLOBALS['ssr'] ?? null)?->stop(0));

beforeEach(function () {
    if (! isset($GLOBALS['ssr'])) {
        $this->markTestSkipped('No SSR bundle: run `npm run build:ssr`.');
    }

    $this->seed(DatabaseSeeder::class);
    // throw_on_error: a failed render fails the test instead of silently falling back to client rendering.
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.throw_on_error' => true, 'app.name' => 'Raumladen']);
});

test('the server sends the rendered page, title included, before any script runs', function (string $path, string $title, string $text) {
    $html = $this->get($path)->assertOk()->getContent();

    expect($html)
        ->toContain('data-server-rendered="true"')
        ->toContain($title)
        ->toContain($text);
})->with([
    'catalogue' => ['/de', '>Bücher – Raumladen</title>', 'Die Kunst des langsamen Lesens</h2>'],
    'issue' => ['/en/products/the-art-of-slow-reading', '>The Art of Slow Reading – Raumladen</title>', 'Add to cart'],
    'legal page' => ['/de/pages/imprint', '>Impressum – Raumladen</title>', 'Angaben gemäß § 5 DDG'],
    'cart' => ['/de/cart', '>Warenkorb – Raumladen</title>', 'Ihr Warenkorb ist leer'],
]);

test('the browser hydrates the server markup without a mismatch', function (string $path) {
    // React reports a hydration mismatch as a console error.
    visit($path)->assertSourceHas('data-server-rendered="true"')->assertNoSmoke();
})->with(['/de', '/de/products/die-kunst-des-langsamen-lesens', '/de/cart', '/de/account/login']);

test('a dark-mode visitor hydrates cleanly too', function () {
    visit('/de')->inDarkMode()->assertNoSmoke();
});

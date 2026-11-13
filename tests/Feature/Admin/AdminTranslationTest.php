<?php

use App\Models\CustomerGroup;
use App\Models\User;

test('every admin index page renders in german', function (string $route) {
    CustomerGroup::factory()->consumer()->create();

    $this->actingAs(User::factory()->admin()->create(['locale' => 'de']))
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'de'));
})->with([
    'admin.dashboard', 'admin.products.index', 'admin.orders.index', 'admin.customers.index',
    'admin.customer-groups.index', 'admin.coupons.index', 'admin.invoices.index', 'admin.datev.index', 'admin.pages.index',
]);

test('every translation key used by the admin, auth and settings code has a german entry', function () {
    $german = json_decode(file_get_contents(lang_path('de.json')), true, flags: JSON_THROW_ON_ERROR);

    $directories = [
        'resources/js/pages/admin', 'resources/js/pages/auth', 'resources/js/pages/settings',
        'resources/js/layouts', 'resources/js/components', 'resources/js/hooks',
        'app/Http/Controllers/Admin', 'app/Http/Controllers/Settings', 'app/Datev',
    ];
    $skip = ['resources/js/layouts/shop-layout', 'resources/js/components/shop/', 'resources/js/components/ui/'];

    $quoted = fn (string $q) => $q === "'" ? "'((?:[^'\\\\]|\\\\.)*)'" : '"((?:[^"\\\\]|\\\\.)*)"';
    $patterns = [];

    foreach (["'", '"'] as $q) {
        $patterns[] = '/(?<![\w.])(?:t|__)\(\s*'.$quoted($q).'/';
        $patterns[] = '/\b(?:title|description|label):\s*'.$quoted($q).'/';
    }

    $missing = [];

    foreach ($directories as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());

            if (! preg_match('/\.(tsx?|php)$/', $path) || str_contains($path, '.test.') || collect($skip)->contains(fn ($s) => str_contains($path, $s))) {
                continue;
            }

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, file_get_contents($file->getPathname()), $matches);

                foreach ($matches[1] as $key) {
                    $key = stripslashes($key);

                    if (! array_key_exists($key, $german)) {
                        $missing[$key] = $path;
                    }
                }
            }
        }
    }

    $this->assertEmpty($missing, 'Missing German entries: '.json_encode($missing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
});

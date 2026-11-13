<?php

namespace App\Http\Middleware;

use App\Cart\Cart;
use App\Models\Page;
use App\Settings\Appearance;
use App\Shop\ProductSearch;
use App\Theme\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    ...$user->toArray(),
                    'is_staff' => $user->isStaff(),
                    // Backend areas of this user's role: decides which navigation and actions are shown.
                    'areas' => $user->backendRole()->areas(),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => app()->getLocale(),
            'locales' => config('shop.locales'),
            'translations' => fn () => $this->translations(app()->getLocale()),
            'shop' => [
                'name' => config('app.name'),
                // For themes that show it; the built-in storefront sets the name in type.
                'logo' => Appearance::logoUrl(),
                'currency' => config('shop.currency'),
            ],
            // The storefront's theme: its class on <html> and what it already provides for everyone.
            'theme' => [
                'class' => Theme::current()->cssClass(),
                'high_contrast' => Theme::current()->provides('high-contrast'),
                'no_motion' => Theme::current()->provides('no-motion'),
            ],
            'cart_count' => fn () => app(Cart::class)->count(),
            // Storefront only: where the browser gets the search index, or null when the catalogue is too large for it.
            'search_index_url' => fn () => $request->routeIs('shop.*') && ProductSearch::fitsInBrowser()
                ? route('shop.search-index', ['locale' => app()->getLocale(), 'v' => ProductSearch::version(), 'g' => $request->user()->customer_group_id ?? 0])
                : null,
            'footer_pages' => fn () => Page::query()->where('is_published', true)->orderBy('id')->get()
                ->map(fn (Page $page) => ['slug' => $page->slug, 'title' => $page->translate('title')])
                ->all(),
        ];
    }

    /**
     * UI strings for the current locale from lang/{locale}.json.
     *
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        $path = lang_path($locale.'.json');

        return File::exists($path) ? File::json($path) : [];
    }
}

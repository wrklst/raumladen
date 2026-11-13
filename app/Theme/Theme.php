<?php

namespace App\Theme;

use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\FileViewFinder;
use RuntimeException;

/**
 * The storefront's theme: how the shop, its invoices and its emails look.
 *
 * A theme is a directory, usually a Composer package in its own Git
 * repository, selected with SHOP_THEME (a package name, or a path). Without
 * SHOP_THEME the default theme, a package the shop requires, is used. A
 * theme may contain:
 *
 *   theme.json                        name, CSS class and what it already provides
 *   resources/css/theme.css           colours, type, fonts (loaded after the shop's CSS)
 *   resources/js/...                  any file here replaces the file of the same
 *                                     path under the shop's resources/js (a page,
 *                                     a layout, a component)
 *   resources/views/...               Blade views that replace the shop's, e.g.
 *                                     invoices/pdf.blade.php or mail/*.blade.php
 *   resources/views/vendor/mail/...   the look of emails (Laravel's mail components)
 *
 * The front end is compiled, so changing the theme means a build and a
 * deploy; vite.config.ts reads the same SHOP_THEME. Views are picked up at
 * runtime by {@see register()}.
 */
class Theme
{
    public const DEFAULT = 'wrklst/raumladen-theme-default';

    /**
     * @param  array{name: string, class: string, provides: list<string>}  $meta
     */
    private function __construct(public readonly string $path, private readonly array $meta) {}

    /**
     * The theme of this application (bound once in AppServiceProvider).
     */
    public static function current(): self
    {
        return app(self::class);
    }

    public static function load(?string $theme): self
    {
        try {
            $path = self::resolvePath($theme);
        } catch (RuntimeException $e) {
            // On the command line the theme may simply not be there yet: Composer
            // boots the application at the end of `composer install`, before a
            // deploy script has added the theme package. A web request never gets
            // here without the theme, because the front-end build stops first.
            if (! app()->runningInConsole()) {
                throw $e;
            }

            $path = self::resolvePath(null);
        }
        $meta = File::exists($path.'/theme.json') ? File::json($path.'/theme.json') : [];
        $name = (string) ($meta['name'] ?? basename($path));

        return new self($path, [
            'name' => $name,
            'class' => 'theme-'.Str::slug((string) ($meta['class'] ?? $name)),
            'provides' => array_values(array_filter((array) ($meta['provides'] ?? []), 'is_string')),
        ]);
    }

    /**
     * A package name resolves to vendor/, anything else is a path (absolute or
     * relative to the application), nothing is the default theme.
     */
    public static function resolvePath(?string $theme): string
    {
        if ($theme === null || $theme === '') {
            $theme = self::DEFAULT;
        }

        foreach ([base_path('vendor/'.$theme), base_path($theme), $theme] as $candidate) {
            if (is_dir($candidate)) {
                return rtrim($candidate, '/');
            }
        }

        throw new RuntimeException("Theme [{$theme}] is not installed: install its package (composer require) or correct SHOP_THEME.");
    }

    public function name(): string
    {
        return $this->meta['name'];
    }

    /**
     * Set on <html> for storefront pages; the theme's CSS hangs on it.
     */
    public function cssClass(): string
    {
        return $this->meta['class'];
    }

    /**
     * What the theme already guarantees for everyone, so the shop does not
     * offer it as an option: "high-contrast", "no-motion".
     */
    public function provides(string $feature): bool
    {
        return in_array($feature, $this->meta['provides'], true);
    }

    /**
     * Let the theme's views win over the shop's: invoices, mails and the look
     * of Laravel's mail components.
     */
    public function register(): void
    {
        $views = $this->path.'/resources/views';

        if (! is_dir($views)) {
            return;
        }

        $finder = View::getFinder();

        if ($finder instanceof FileViewFinder) {
            $finder->prependLocation($views);
            // Views found before the theme was registered are remembered by path.
            $finder->flush();
        }

        if (is_dir($views.'/vendor/mail')) {
            config(['mail.markdown.paths' => [$views.'/vendor/mail', ...config('mail.markdown.paths', [])]]);
            app()->forgetInstance(Markdown::class);
        }
    }
}

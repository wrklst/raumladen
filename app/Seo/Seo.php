<?php

namespace App\Seo;

use Illuminate\Support\Str;

/**
 * Builds the `seo` page prop that the Blade root template renders into <head>
 * (title, description, canonical, hreflang, Open Graph, JSON-LD) so crawlers
 * get it even without SSR.
 */
class Seo
{
    /**
     * @param  array<string, string>  $alternates  locale => absolute URL
     * @param  list<array<string, mixed>>  $jsonLd  additional schema.org nodes
     * @return array<string, mixed>
     */
    public static function make(
        string $title,
        ?string $description,
        string $canonical,
        array $alternates,
        array $jsonLd = [],
        ?string $image = null,
        string $type = 'website',
        bool $index = true,
    ): array {
        $locale = app()->getLocale();
        $default = config('shop.locales')[0];

        if (isset($alternates[$default])) {
            $alternates['x-default'] = $alternates[$default];
        }

        return [
            'title' => $title,
            'description' => Str::limit(trim(strip_tags($description ?? '')), 160, ''),
            'canonical' => $canonical,
            'alternates' => $alternates,
            'image' => $image,
            'type' => $type,
            'locale' => $locale,
            'index' => $index,
            'json_ld' => StructuredData::graph([
                StructuredData::organization(),
                StructuredData::website($locale),
                ...$jsonLd,
            ]),
        ];
    }

    /**
     * Same route in every locale.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, string>
     */
    public static function alternates(string $route, array $parameters = []): array
    {
        $urls = [];

        foreach (config('shop.locales') as $locale) {
            $urls[$locale] = route($route, ['locale' => $locale, ...$parameters]);
        }

        return $urls;
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Support\Arr;

/**
 * Translatable attributes are JSON columns shaped {"de": "...", "en": "..."}
 * and must be cast to array on the model.
 */
trait HasTranslations
{
    public function translate(string $attribute, ?string $locale = null): ?string
    {
        $values = $this->getAttribute($attribute);

        if (! is_array($values)) {
            return $values;
        }

        $locale ??= app()->getLocale();

        return $values[$locale]
            ?? $values[config('app.fallback_locale')]
            ?? Arr::first($values);
    }
}

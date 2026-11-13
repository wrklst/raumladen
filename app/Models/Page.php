<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Settings\PageVariables;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Content pages (imprint, terms, privacy, withdrawal). Body is Markdown and
 * may contain placeholders such as :name or :vat_id, which are filled from
 * the shop settings when the page is shown.
 *
 * @property int $id
 * @property string $slug
 * @property array<string, string> $title
 * @property array<string, string>|null $body
 * @property bool $is_published
 */
#[Fillable(['slug', 'title', 'body', 'is_published'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasTranslations;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function renderedBody(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        // Placeholders such as :name are filled from the shop settings (App\Settings\PageVariables).
        return (string) Str::markdown(strtr($this->translate('body', $locale) ?? '', PageVariables::for($this, $locale)), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // Addresses are written one line per row.
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'is_published' => 'boolean',
        ];
    }
}

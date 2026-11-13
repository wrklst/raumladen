<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Content pages (imprint, terms, privacy, withdrawal). Body is Markdown.
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
        return (string) Str::markdown($this->translate('body', $locale) ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
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

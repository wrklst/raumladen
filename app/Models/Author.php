<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Shop\ProductSearch;
use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An author with a few lines about them, shown above their books. A book
 * names its authors as text (specs.authors); the author is found by that name.
 *
 * @property int $id
 * @property string $name
 * @property array<string, string|null>|null $bio
 */
#[Fillable(['name', 'bio'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * The author a book or a listing names, whatever the capitals and accents.
     */
    public static function named(string $name): ?self
    {
        $wanted = ProductSearch::normalize($name);

        // ponytail: reads every author; a normalised column and an index when there are thousands.
        return $wanted === '' ? null : self::query()->get()->first(fn (self $author): bool => ProductSearch::normalize($author->name) === $wanted);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['bio' => 'array'];
    }
}

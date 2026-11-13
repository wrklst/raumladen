<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\TaxClass;
use App\Models\Concerns\HasTranslations;
use App\Shop\ProductSearch;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ProductType $type
 * @property string|null $sku
 * @property array<string, string> $slug
 * @property array<string, string> $title
 * @property array<string, string>|null $description
 * @property TaxClass $tax_class
 * @property int $price_cents
 * @property string $currency
 * @property int $stock
 * @property bool $track_stock
 * @property int|null $weight_grams
 * @property array<int, string>|null $images
 * @property array<string, mixed>|null $specs
 * @property bool $is_active
 * @property Carbon|null $published_at
 */
#[Fillable([
    'type', 'sku', 'slug', 'title', 'description', 'tax_class', 'price_cents', 'currency',
    'stock', 'track_stock', 'weight_grams', 'images', 'specs', 'is_active', 'published_at',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    /**
     * The search index is precomputed from the catalogue: any change to a
     * product makes it stale.
     */
    protected static function booted(): void
    {
        static::saved(ProductSearch::forget(...));
        // Stock is changed with increment/decrement, which fire "updated" only.
        static::updated(ProductSearch::forget(...));
        static::deleted(ProductSearch::forget(...));
        static::restored(ProductSearch::forget(...));
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    #[Scope]
    protected function whereSlug(Builder $query, string $slug, ?string $locale = null): Builder
    {
        return $query->where('slug->'.($locale ?? app()->getLocale()), $slug);
    }

    /**
     * Storefront routes resolve products by the slug of the current locale.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->newQuery()->active()->whereSlug($value)->first();
    }

    /**
     * Gross list price in cents for a customer group, falling back to the default price.
     */
    public function priceFor(?CustomerGroup $group): int
    {
        if ($group === null) {
            return $this->price_cents;
        }

        $price = $this->relationLoaded('prices')
            ? $this->prices->firstWhere('customer_group_id', $group->id)
            : $this->prices()->where('customer_group_id', $group->id)->first();

        return $price->price_cents ?? $this->price_cents;
    }

    public function hasStockFor(int $quantity): bool
    {
        return ! $this->track_stock || $this->stock >= $quantity;
    }

    public function isDigital(): bool
    {
        return $this->type->isDigital();
    }

    public function urlFor(string $locale): string
    {
        return route('shop.products.show', [
            'locale' => $locale,
            'product' => $this->translate('slug', $locale),
        ]);
    }

    public function imageUrl(): ?string
    {
        $image = $this->images[0] ?? null;

        return $image === null ? null : asset('storage/'.$image);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'tax_class' => TaxClass::class,
            'slug' => 'array',
            'title' => 'array',
            'description' => 'array',
            'images' => 'array',
            'specs' => 'array',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}

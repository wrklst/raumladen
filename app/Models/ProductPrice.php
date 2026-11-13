<?php

namespace App\Models;

use App\Shop\ProductSearch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $customer_group_id
 * @property int $price_cents
 */
#[Fillable(['product_id', 'customer_group_id', 'price_cents'])]
class ProductPrice extends Model
{
    /**
     * Group prices are part of the browser's search index.
     */
    protected static function booted(): void
    {
        static::saved(ProductSearch::forget(...));
        static::deleted(ProductSearch::forget(...));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<CustomerGroup, $this>
     */
    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}

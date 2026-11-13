<?php

namespace App\Models;

use App\Enums\TaxClass;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $name
 * @property string|null $sku
 * @property int $quantity
 * @property int $unit_price_cents
 * @property TaxClass $tax_class
 * @property float $tax_rate
 * @property string $tax_category
 * @property int $discount_cents
 * @property int $line_net_cents
 * @property int $line_tax_cents
 * @property int $line_gross_cents
 */
#[Fillable([
    'product_id', 'name', 'sku', 'quantity', 'unit_price_cents', 'tax_class', 'tax_rate', 'tax_category',
    'discount_cents', 'line_net_cents', 'line_tax_cents', 'line_gross_cents',
])]
class OrderItem extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_class' => TaxClass::class,
            'tax_rate' => 'float',
        ];
    }
}

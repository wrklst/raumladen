<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * @mixin Product
 */
class ProductDetailResource extends ProductCardResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'sku' => $this->sku,
            'description' => $this->translate('description'),
            'images' => array_map(fn (string $path) => asset('storage/'.$path), $this->images ?? []),
            'specs' => $this->specs ?? [],
            'spec_fields' => array_keys($this->type->attributeFields()),
            'stock' => $this->track_stock ? $this->stock : null,
            'weight_grams' => $this->weight_grams,
        ];
    }
}

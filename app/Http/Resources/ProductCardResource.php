<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Shop\CurrentCustomer;
use App\Tax\TaxMath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $group = CurrentCustomer::group();
        $gross = $this->priceFor($group);
        $homeRate = $this->tax_class->rateIn(config('shop.tax.home_country'));

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->translate('title'),
            'slug' => $this->translate('slug'),
            'url' => $this->urlFor(app()->getLocale()),
            'price_cents' => $group->prices_include_tax ? $gross : (int) round(TaxMath::netOf($gross, $homeRate)),
            'prices_include_tax' => $group->prices_include_tax,
            'tax_rate' => $homeRate,
            'currency' => $this->currency,
            'image' => $this->imageUrl(),
            'in_stock' => $this->hasStockFor(1),
            'authors' => $this->specs['authors'] ?? null,
        ];
    }
}

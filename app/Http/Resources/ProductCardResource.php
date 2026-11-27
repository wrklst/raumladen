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
        // A small business (§ 19 UStG) charges no VAT: one price for everyone, and no rate to name beside it.
        $withoutVat = (bool) config('shop.tax.small_business');

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->translate('title'),
            'slug' => $this->translate('slug'),
            'url' => $this->urlFor(app()->getLocale()),
            'price_cents' => $withoutVat || $group->prices_include_tax ? $gross : (int) round(TaxMath::netOf($gross, $homeRate)),
            'prices_include_tax' => $withoutVat || $group->prices_include_tax,
            'tax_rate' => $withoutVat ? null : $homeRate,
            'currency' => $this->currency,
            'image' => $this->imageUrl(),
            'in_stock' => $this->hasStockFor(1),
            'authors' => $this->specs['authors'] ?? null,
            'series' => $this->specs['series'] ?? null,
            // For themes that set the cover in type from the book's own text.
            // HTML, cleaned (see App\Support\Html): pages put it in as it is.
            'description' => $this->descriptionHtml(),
            'isbn' => $this->specs['isbn'] ?? null,
            'publisher' => $this->specs['publisher'] ?? null,
            'back_cover' => $this->backCoverUrl(),
        ];
    }
}

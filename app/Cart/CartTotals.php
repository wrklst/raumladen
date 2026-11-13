<?php

namespace App\Cart;

use App\Enums\TaxTreatment;
use App\Models\Coupon;

/**
 * Result of CartCalculator. "Display" amounts are in the customer's price
 * basis: gross for consumer groups, net for business groups.
 *
 * @phpstan-type Line array{product_id: int, name: string, sku: string|null, quantity: int, unit_price_cents: int, tax_class: string, tax_rate: float, tax_category: string, discount_cents: int, line_net_cents: int, line_tax_cents: int, line_gross_cents: int, line_display_cents: int, image: string|null, slug: string|null, url: string, digital: bool}
 * @phpstan-type TaxGroup array{rate: float, category: string, net: int, tax: int, gross: int}
 */
final readonly class CartTotals
{
    /**
     * @param  list<Line>  $lines
     * @param  list<TaxGroup>  $taxSummary
     */
    public function __construct(
        public array $lines,
        public TaxTreatment $treatment,
        public bool $pricesIncludeTax,
        public string $country,
        public bool $vatIdValid,
        public int $subtotalDisplayCents,
        public int $subtotalNetCents,
        public int $discountCents,
        public ?Coupon $coupon,
        public ?string $couponError,
        public int $shippingDisplayCents,
        public int $shippingNetCents,
        public int $shippingTaxCents,
        public float $shippingTaxRate,
        public int $taxCents,
        public int $totalCents,
        public array $taxSummary,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => $this->lines,
            'treatment' => $this->treatment->value,
            'tax_note' => $this->treatment->note(app()->getLocale()),
            'prices_include_tax' => $this->pricesIncludeTax,
            'country' => $this->country,
            'vat_id_valid' => $this->vatIdValid,
            'subtotal_display_cents' => $this->subtotalDisplayCents,
            'discount_cents' => $this->discountCents,
            'coupon_code' => $this->coupon?->code,
            'coupon_error' => $this->couponError,
            'shipping_display_cents' => $this->shippingDisplayCents,
            'tax_cents' => $this->taxCents,
            'total_cents' => $this->totalCents,
            'tax_summary' => $this->taxSummary,
        ];
    }
}

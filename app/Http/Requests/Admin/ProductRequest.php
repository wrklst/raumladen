<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductType;
use App\Enums\TaxClass;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $default = config('shop.locales')[0];
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'type' => ['required', Rule::enum(ProductType::class)],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique(Product::class)->ignore($productId)],
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:255'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'array'],
            'slug.*' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:20000'],
            'tax_class' => ['required', Rule::enum(TaxClass::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'stock' => ['required', 'integer', 'min:0'],
            'track_stock' => ['boolean'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'specs' => ['nullable', 'array'],
            'specs.*' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'existing_images' => ['nullable', 'array'],
            'existing_images.*' => ['string'],
            'group_prices' => ['nullable', 'array'],
            'group_prices.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Attributes for Product::create()/update(). Empty slugs derive from the title.
     *
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        $titles = array_filter($this->validated('title'));
        $slugs = [];

        foreach ($titles as $locale => $title) {
            $slugs[$locale] = $this->validated("slug.{$locale}") ?: Str::slug($title);
        }

        return [
            'type' => $this->validated('type'),
            'sku' => $this->validated('sku') ?: null,
            'title' => $titles,
            'slug' => $slugs,
            'description' => array_filter($this->validated('description') ?? []),
            'tax_class' => $this->validated('tax_class'),
            'price_cents' => (int) round((float) $this->validated('price') * 100),
            'stock' => $this->validated('stock'),
            'track_stock' => $this->boolean('track_stock'),
            'weight_grams' => $this->validated('weight_grams'),
            'specs' => array_filter($this->validated('specs') ?? [], fn ($v) => $v !== null && $v !== ''),
            'is_active' => $this->boolean('is_active'),
            'published_at' => $this->validated('published_at'),
        ];
    }
}

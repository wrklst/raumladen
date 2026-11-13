<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\TaxClass;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'type' => ProductType::Book,
            'sku' => fake()->unique()->bothify('BK-####'),
            'slug' => ['de' => Str::slug($title), 'en' => Str::slug($title)],
            'title' => ['de' => $title, 'en' => $title],
            'description' => ['de' => fake()->paragraph(), 'en' => fake()->paragraph()],
            'tax_class' => TaxClass::Reduced,
            'price_cents' => fake()->numberBetween(990, 4990),
            'currency' => 'EUR',
            'stock' => 25,
            'track_stock' => true,
            'weight_grams' => fake()->numberBetween(200, 900),
            'images' => null,
            'specs' => [
                'isbn' => fake()->isbn13(),
                'authors' => fake()->name(),
                'pages' => (string) fake()->numberBetween(80, 480),
                'binding' => 'Paperback',
                'language' => 'de',
            ],
            'is_active' => true,
            'published_at' => now()->subDay(),
        ];
    }

    public function standardRate(): static
    {
        return $this->state(['tax_class' => TaxClass::Standard]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

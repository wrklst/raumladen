<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('group????'),
            'name' => fake()->words(2, true),
            'prices_include_tax' => true,
            'is_default' => false,
        ];
    }

    public function consumer(): static
    {
        return $this->state(['code' => 'b2c', 'name' => 'Consumers', 'prices_include_tax' => true, 'is_default' => true]);
    }

    public function business(): static
    {
        return $this->state(['code' => 'b2b', 'name' => 'Business', 'prices_include_tax' => false, 'is_default' => false]);
    }
}

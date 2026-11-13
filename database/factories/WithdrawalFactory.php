<?php

namespace Database\Factories;

use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'B-'.fake()->numerify('#####'),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'statement' => fake()->sentence(),
            'locale' => 'de',
        ];
    }
}

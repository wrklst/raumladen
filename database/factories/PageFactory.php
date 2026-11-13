<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->unique()->words(2);
        $title = is_array($words) ? implode(' ', $words) : $words;

        return [
            'slug' => fake()->unique()->slug(2),
            'title' => ['de' => ucfirst($title), 'en' => ucfirst($title)],
            'body' => ['de' => fake()->paragraph(), 'en' => fake()->paragraph()],
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}

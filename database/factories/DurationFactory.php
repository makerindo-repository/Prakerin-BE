<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Duration>
 */
class DurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'duration_value' => $this->faker->numberBetween(1, 12),
            'duration_unit' => $this->faker->randomElement(['day', 'month', 'year']),
            'is_accepted' => true,
        ];
    }
}

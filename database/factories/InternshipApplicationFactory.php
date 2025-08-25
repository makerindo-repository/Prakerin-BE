<?php

namespace Database\Factories;

use App\Models\InternshipApplication;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InternshipApplication>
 */
class InternshipApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internship_application_id' => InternshipApplication::inRandomOrder()->first()?->id ?? InternshipApplication::factory(),
            'test_id' => Test::inRandomOrder()->first()?->id ?? Test::factory(),
            'score' => $this->faker->numberBetween(0, 100),
            'status' => $this->faker->randomElement(['pending', 'passed', 'failed']),
            'taken_at' => $this->faker->dateTimeBetween('-1 month', 'now'),

        ];
    }
}

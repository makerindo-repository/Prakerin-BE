<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Test>
 */
class TestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'test' => $this->faker->paragraph(2),
            'duration' => $this->faker->numberBetween(30, 120), // dalam menit
            'passing_score' => $this->faker->numberBetween(50, 100),
        ];
    }
}

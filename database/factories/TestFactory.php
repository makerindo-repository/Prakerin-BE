<?php

namespace Database\Factories;

use App\Models\Company;
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
            'company_id' => Company::inRandomOrder()->first()->id ?? Company::factory(),
            'title' => $this->faker->sentence(3),
            'test' => $this->faker->paragraph(2),
            'type' => $this->faker->randomElement(['theory', 'practice']),
        ];
    }
}

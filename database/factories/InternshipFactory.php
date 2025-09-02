<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InternshipApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Internship>
 */
class InternshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 month', '+1 month');
        $end = (clone $start)->modify('+' . rand(1, 6) . ' months');

        return [
            'id' => (string) Str::uuid(),
            'internship_application_id' => InternshipApplication::inRandomOrder()->first()->id ?? InternshipApplication::factory(),
            'start_date' => $start,
            'end_date' => $end,
            'is_completed' => $this->faker->boolean(),
        ];
    }
}

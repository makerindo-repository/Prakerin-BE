<?php

namespace Database\Factories;

use App\Models\Company;
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
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(4),
            'start_date' => $start,
            'end_date' => $end,
            'grade' => $this->faker->randomElement(['SMK', 'Mahasiswa', 'all']),
            'bidang' => $this->faker->randomElement(['IT', 'Embedding', 'Other']),
            'type' => $this->faker->randomElement(['wfh', 'full time', 'hybrid']),
            'company_id' => Company::inRandomOrder()->first()?->id ?? Company::factory(),
            'kuota' => $this->faker->numberBetween(1, 10),
        ];
    }
}

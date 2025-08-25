<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobOpening>
 */
class JobOpeningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'company_id' => Company::inRandomOrder()->first()?->id ?? Company::factory(),
            'field_id' => Field::inRandomOrder()->first()?->id ?? Field::factory(),
            'title' => $this->faker->jobTitle(),
            'description' => $this->faker->paragraph(3),
            'duration' => $this->faker->numberBetween(1, 12),
            'is_paid' => $this->faker->boolean(),
            'grade' => $this->faker->randomElement(['SMK', 'Mahasiswa', 'all']),
            'type' => $this->faker->randomElement(['wfh', 'full time', 'hybrid']),
            'qouta' => $this->faker->numberBetween(1, 10),
            'is_available' => $this->faker->boolean(),
        ];
    }
}

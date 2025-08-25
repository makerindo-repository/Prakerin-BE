<?php

namespace Database\Factories;

use App\Models\CurriculumVitae;
use App\Models\InternshipApplication;
use App\Models\JobOpening;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InternshipApplicationTest>
 */
class InternshipApplicationTestFactory extends Factory
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
            'is_passed' => $this->faker->boolean(),
        ];

    }
}

<?php

namespace Database\Factories;

use App\Models\CurriculumVitae;
use App\Models\InternshipApplication;
use App\Models\JobOpening;
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
            'curriculum_vitae_id' => CurriculumVitae::inRandomOrder()->first()?->id ?? CurriculumVitae::factory(),
            'job_opening_id' => JobOpening::inRandomOrder()->first()?->id ?? JobOpening::factory(),
            'status' => $this->faker->randomElement(['in_progress', 'accepted', 'rejected']),
            'cover_letter' => $this->faker->text(),
        ];
    }
}

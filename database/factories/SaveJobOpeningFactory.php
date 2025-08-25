<?php

namespace Database\Factories;

use App\Models\JobOpening;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaveJobOpening>
 */
class SaveJobOpeningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::inRandomOrder()->first()?->id ?? Student::factory(),
            'job_opening_id' => JobOpening::inRandomOrder()->first()?->id ?? JobOpening::factory(),
        ];
    }
}

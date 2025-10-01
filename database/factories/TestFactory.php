<?php

namespace Database\Factories;

use App\Models\User;
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
            'company_id' => User::where('email', 'supercompany@makerindo.id')->first()->company->id,
            'title' => $this->faker->sentence(3),
            'link' => $this->faker->url(),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['theory', 'practice', 'other']),
        ];
    }
}

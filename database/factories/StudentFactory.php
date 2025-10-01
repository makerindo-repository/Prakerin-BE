<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\School;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::inRandomOrder()->first()?->id ?? School::factory(),
            'user_id' => User::factory()->create(['role' => 'student']),
            'name' => $this->faker->name(),
            'date_of_birth' => $this->faker->date('Y-m-d', '-18 years'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'phone_number' => $this->faker->regexify('\+62 8[0-9]{2}-[0-9]{4}-[0-9]{4}'),
            'address' => $this->faker->address(),
            'is_verified' => true,
        ];
    }
}

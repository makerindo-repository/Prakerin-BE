<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        static $schoolNumber = 1;

        return [
            'user_id' => User::factory()->create(["role" => "school"]),
            'name' => 'School ' . $schoolNumber++,
            'address' => $this->faker->address(),
            'phone_number' => $this->faker->phoneNumber(),
            'is_verified' => $this->faker->boolean(),
        ];
    }
}

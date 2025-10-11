<?php

namespace Database\Factories;

use App\Models\CityRegency;
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
            'type' => $this->faker->randomElement(['school', 'university']),
            // 'type' => 'school',
            'address' => $this->faker->address(),
            'phone_number' => $this->faker->regexify('\+62 8[0-9]{2}-[0-9]{4}-[0-9]{4}'),
            // 'is_verified' => $this->faker->boolean(70),
            'is_verified' => true,
            'city_regency_id' => CityRegency::inRandomOrder()->first()?->id ?? CityRegency::factory(),

        ];
    }
}

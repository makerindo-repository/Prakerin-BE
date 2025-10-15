<?php

namespace Database\Factories;

use App\Models\CityRegency;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(["role" => "company"]),
            'city_regency_id' => CityRegency::inRandomOrder()->first()?->id ?? CityRegency::factory(),
            'sector_id' => Sector::inRandomOrder()->first()?->id ?? Sector::factory(),
            'name' => $this->faker->company(),
            'description' => $this->faker->paragraph(),
            'address' => $this->faker->address(),
            'phone_number' => $this->faker->regexify('/^\+628[0-9]{8,10}$/'),
            'is_verified' => $this->faker->boolean(70),
        ];
    }
}

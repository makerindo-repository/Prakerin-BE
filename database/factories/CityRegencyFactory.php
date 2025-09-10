<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CityRegency>
 */
class CityRegencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::inRandomOrder()->first()?->id ?? Province::factory(),
            'name' => $this->faker->city(),
            'is_accepted' => true,
        ];
    }
}

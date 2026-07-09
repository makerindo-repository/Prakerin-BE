<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Duration;
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
            'description' => [
                'blocks' => [
                    [
                        'id' => (string) Str::random(10),
                        'type' => 'paragraph',
                        'data' => [
                            'text' => $this->faker->paragraph(2)
                        ]
                    ],
                    [
                        'id' => (string) Str::random(10),
                        'type' => 'header',
                        'data' => [
                            'text' => 'Persyaratan / Kualifikasi:',
                            'level' => 3
                        ]
                    ],
                    [
                        'id' => (string) Str::random(10),
                        'type' => 'list',
                        'data' => [
                            'style' => 'unordered',
                            'items' => [
                                ['content' => 'Pendidikan minimal SMK/Siswa aktif/Mahasiswa tingkat akhir.'],
                                ['content' => 'Memiliki ketertarikan kuat di bidang terkait.'],
                                ['content' => 'Mampu bekerja sama dalam tim dan komunikatif.'],
                                ['content' => 'Disiplin dan bertanggung jawab terhadap tugas.']
                            ]
                        ]
                    ]
                ]
            ],
            'duration_id' => Duration::inRandomOrder()->first()?->id ?? Duration::factory(),
            'is_paid' => $this->faker->boolean(),
            'grade' => $this->faker->randomElement(['smk', 'mahasiswa', 'all']),
            'type' => $this->faker->randomElement(['full_time', 'part_time']),
            'location' => $this->faker->randomElement(['onsite', 'remote', 'hybrid']),
            'qouta' => $this->faker->numberBetween(1, 10),
            'is_available' => $this->faker->boolean(),
            'start_date' => $start = $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'end_date' => \Carbon\Carbon::parse($start)->addMonths(3)->format('Y-m-d'),
            'closing_date' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
        ];
    }
}

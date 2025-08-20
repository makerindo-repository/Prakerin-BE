<?php

namespace Database\Seeders;

use App\Models\CityRegency;
use App\Models\Company;
use App\Models\Field;
use App\Models\Internship;
use App\Models\Province;
use App\Models\School;
use App\Models\Sector;
use App\Models\Student;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'role' => 'super_admin',
        ]);

        School::factory(20)->create();

        Student::factory(10)->create();

        Province::factory(5)->create();
        CityRegency::factory(10)->create();
        Sector::factory(5)->create();
        Company::factory(10)->create();

        Field::factory(5)->create();
        // // Internship::factory(20)->create();
        // $this->call([
        //     InternshipSeeder::class,
        // ]);
    }
}

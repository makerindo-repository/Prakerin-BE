<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Internship;
use App\Models\School;
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
        // User::factory(10)->create();

        User::factory()->create([
            // 'name' => 'Test User',
            'email' => 'user@example.com',
            'role' => 'super_admin',
            'password' => bcrypt('string')
        ]);
        // User::factory()->create([
        //     'name' => 'Test School',
        //     'email' => 'testschool@example.com',
        //     'role' => 'school',
        //     'password' => bcrypt('#admin123')
        // ]);
        School::factory(5)->create();
        Student::factory(10)->create();
        Company::factory(15)->create();
        // Internship::factory(20)->create();
        $this->call([
            InternshipSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\CityRegency;
use App\Models\Company;
use App\Models\CurriculumVitae;
use App\Models\Duration;
use App\Models\Field;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\InternshipApplicationTest;
use App\Models\JobOpening;
use App\Models\Major;
use App\Models\Province;
use App\Models\Report;
use App\Models\ReportTask;
use App\Models\ReportTaskMessage;
use App\Models\SaveJobOpening;
use App\Models\School;
use App\Models\Sector;
use App\Models\Student;
use App\Models\Task;
use App\Models\Test;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Queue\Jobs\Job;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        Province::factory(5)->create();
        CityRegency::factory(5)->create();
        Sector::factory(5)->create();
        Major::factory(10)->create();
        Field::factory(10)->create();


        User::factory()->create([
            'email' => 'user@example.com',
            'role' => 'super_admin',
        ]);

        School::factory(3)->create([
        ]);

        Company::factory(3)->create([
        ]);

        Student::factory(9)->create([

        ]);





        Field::factory(5)->create();
        CurriculumVitae::factory(18)->create();

        Duration::factory(5)->create();

        JobOpening::factory(10)->create();
        // InternshipApplication::factory(20)->create();

        // Test::factory(10)->create();
        // InternshipApplicationTest::factory(20)->create();

        Internship::factory(1)->create();

        SaveJobOpening::factory(5)->create();

        Task::factory(5)->create();
        // ReportTask::factory(5)->create();
        // ReportTaskMessage::factory(20)->create();



    }
}

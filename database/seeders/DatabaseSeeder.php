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
        Duration::factory(5)->create();



        User::factory()->create([
            'email' => 'superadmin@makerindo.id',
            'role' => 'super_admin',
        ]);

        $school = User::factory()->create([
            'email' => 'superschool@makerindo.id',
            'role' => 'school',
        ]);
        $student = User::factory()->create([
            'email' => 'superstudent@makerindo.id',
            'role' => 'student',
        ]);
        $company = User::factory()->create([
            'email' => 'supercompany@makerindo.id',
            'role' => 'company',
        ]);


        $schoolId = School::factory()->create([
            'user_id' => $school->id,
        ]);

        Company::factory()->create([
            'user_id' => $company->id,
        ]);

        Student::factory()->create([
            'user_id' => $student->id,
            'school_id' => $schoolId->id,
        ]);

        School::factory(3)->create([
        ]);

        Company::factory(3)->create([
        ]);

        Student::factory(9)->create([

        ]);





        // CurriculumVitae::factory(18)->create();


        // JobOpening::factory(10)->create();
        // InternshipApplication::factory(20)->create();

        // Test::factory(10)->create();
        // InternshipApplicationTest::factory(20)->create();

        // Internship::factory(1)->create();

        // SaveJobOpening::factory(5)->create();

        // Task::factory(5)->create();
        // ReportTask::factory(5)->create();
        // ReportTaskMessage::factory(20)->create();



    }
}

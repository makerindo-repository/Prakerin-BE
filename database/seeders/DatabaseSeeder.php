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
use App\Models\Hompage;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Facades\Hash;

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

        $homPage = [
            [
                'name' => 'title-landing-1',
                'value' => 'Raih Pengalaman Nyata,Bangun Karier Impianmu!'
            ],
            [
                'name' => 'desc-landing-1',
                'value' => 'Raih Pengalaman Nyata,Bangun Karier Impianmu!'
            ],
            [
                'name' => 'title-landing-2',
                'value' => 'Kenapa Harus Magang Melalui Prakerin?'
            ],
            [
                'name' => 'subtitle-landing-2',
                'value' => 'Prakerin hadir sebagai solusi terpercaya untuk menjembatani talenta muda dengan perusahaan berkualitas.'
            ],
            [
                'name' => 'title-content-landing-2-1',
                'value' => 'Magang Terverifikasi'
            ],
            [
                'name' => 'desc-content-landing-2-1',
                'value' => 'Semua lowongan magang di Prakerin sudah melalui proses verifikasi.'
            ],
            [
                'name' => 'icon-content-landing-2-1',
                'value' => 'CheckCircle2'
            ],
            [
                'name' => 'title-content-landing-2-2',
                'value' => 'Pendampingan Profesional'
            ],
            [
                'name' => 'desc-content-landing-2-2',
                'value' => 'Prakerin mendampingi setiap langkahmu agar pengalaman magang berjalan lancar.'
            ],
            [
                'name' => 'icon-content-landing-2-2',
                'value' => 'Users2'
            ],
            [
                'name' => 'title-content-landing-2-3',
                'value' => 'Bangun Portofolio Nyata'
            ],
            [
                'name' => 'desc-content-landing-2-3',
                'value' => 'Dapatkan pengalaman kerja yang aktual industri dan perkuat rekam jejak profesional.'
            ],
            [
                'name' => 'icon-content-landing-2-3',
                'value' => 'Inbox'
            ],
            [
                'name' => 'title-landing-3',
                'value' => 'Mitra'
            ],
            [
                'name' => 'subtitle-landing-3',
                'value' => 'Wujudkan magang di perusahaan impian anda!'
            ],
            [
                'name' => 'title-landing-4',
                'value' => 'Feedback Siswa/Mahasiswa'
            ],
            [
                'name' => 'subtitle-landing-4',
                'value' => 'Apa kata mereka yang sudah magang melalui Prakerin?'
            ],
            [
                'name' => 'title-landing-5',
                'value' => "Let's Grow Together!",
            ],
            [
                'name' => 'subtitle-landing-5',
                'value' => 'Mulai wujudkan impianmu! Prakerin siap mendukung langkah kariermu.'
            ],
            [
                'name' => 'title-landing-6',
                'value' => 'Sering di tanyakan',
            ],
            [
                'name' => 'subtitle-landing-6',
                'value' => 'Punya ide, pertanyaan, atau sekadar ingin menyapa seputar magang? Berikut adalah pertanyaan yang sering diajukan:'
            ],
            [
                'name' => 'title-landing-7',
                'value' => 'Hubungi Kami'
            ],
            [
                'name' => 'subtitle-landing-7',
                'value' => 'Punya ide, pertanyaan, atau sekadar ingin menyapa seputar magang? Kami senang mendengarnya! Silakan hubungi kami kapan saja.'
            ],
            [
                'name' => 'title-about-1',
                'value' => '[Belum Di isi]',
            ],
            [
                'name' => 'desc-about-1',
                'value' => '[Belum Di isi isi]'
            ],
        ];

        foreach ($homPage as $item) {
            Hompage::create(
                $item
            );
        }


        User::factory()->create([
            'email' => 'superadmin@makerindo.id',
            'password' => Hash::make('rahasia123'),
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
            'name' => 'super school',
            'user_id' => $school->id,
        ]);

        School::factory()->create([
            'name' => 'SMKN 3 Banjar',
        ]);
        School::factory()->create([
            'name' => 'SMKS Wikrama Bogor',
        ]);
        School::factory()->create([
            'name' => 'SMKN 2 Sukabumi',
        ]);
        School::factory()->create([
            'name' => 'SMKN 1 Katapang',
        ]);
        School::factory()->create([
            'name' => 'SMKN 1 Subang',
        ]);
  

        Company::factory()->create([
            'user_id' => $company->id,
        ]);

        Student::factory()->create([
            'user_id' => $student->id,
            'school_id' => $schoolId->id,
        ]);

        // School::factory(20)->create();

        Company::factory(20)->create();

        Student::factory(20)->create();

        Test::factory(20)->create();

        // Task::factory(20)->create();





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

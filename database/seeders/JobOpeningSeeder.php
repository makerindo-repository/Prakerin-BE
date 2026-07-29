<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobOpening;
use App\Models\Company;
use App\Models\Field;
use App\Models\Duration;
use App\Models\Test;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JobOpeningSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Extend closing_date for all existing job openings so they are active again
        JobOpening::query()->update([
            'closing_date' => Carbon::now()->addDays(45)->toDateString(),
            'start_date' => Carbon::now()->addDays(7)->toDateString(),
            'end_date' => Carbon::now()->addDays(97)->toDateString(),
            'is_available' => true,
        ]);

        // 2. Fetch companies
        $companies = Company::all()->keyBy('name');
        if ($companies->isEmpty()) {
            echo "No companies found in database! Please run DatabaseSeeder first.\n";
            return;
        }

        // Helper to get or create field
        $getFieldId = function ($fieldName) {
            return Field::firstOrCreate(
                ['name' => $fieldName],
                ['is_accepted' => true]
            )->id;
        };

        // Helper to get or create duration
        $getDurationId = function ($months) {
            $name = "{$months} month";
            return Duration::firstOrCreate(
                ['duration_value' => $months, 'duration_unit' => 'month'],
                ['name' => $name, 'is_accepted' => true]
            )->id;
        };

        // New job openings list for testing & video presentation
        $newJobs = [
            [
                'company_name' => 'PT. Makerindo Prima Solusi',
                'field_name' => 'Web Development',
                'duration_months' => 3,
                'title' => 'Internship Frontend Developer (Vue.js & Next.js)',
                'grade' => 'all',
                'type' => 'full_time',
                'location' => 'hybrid',
                'qouta' => 4,
                'stipend' => 'Rp 1.800.000 - Rp 2.500.000 / bulan',
                'desc' => 'Bergabunglah dengan tim Frontend Makerindo untuk membangun antarmuka web modern berbasis Vue 3 & Next.js yang responsif dan interaktif.'
            ],
            [
                'company_name' => 'PT. Makerindo Prima Solusi',
                'field_name' => 'Quality Assurance',
                'duration_months' => 3,
                'title' => 'Internship QA & Automated Testing Engineer',
                'grade' => 'smk',
                'type' => 'full_time',
                'location' => 'onsite',
                'qouta' => 3,
                'stipend' => 'Rp 1.500.000 - Rp 2.000.000 / bulan',
                'desc' => 'Pelajari dan terapkan pengujian perangkat lunak otomatis menggunakan Cypress & Postman pada aplikasi enterprise.'
            ],
            [
                'company_name' => 'PT. Telkom Indonesia (Persero) Tbk',
                'field_name' => 'Cloud Computing & DevOps',
                'duration_months' => 6,
                'title' => 'Internship Cloud & DevOps Engineer',
                'grade' => 'mahasiswa',
                'type' => 'full_time',
                'location' => 'onsite',
                'qouta' => 5,
                'stipend' => 'Rp 3.000.000 - Rp 4.000.000 / bulan',
                'desc' => 'Pelajari pengelolaan infrastruktur Cloud (AWS/GCP), CI/CD Pipeline, Docker, dan Kubernetes bersama divisi IT Telkom.'
            ],
            [
                'company_name' => 'PT. Telkom Indonesia (Persero) Tbk',
                'field_name' => 'Cyber Security',
                'duration_months' => 6,
                'title' => 'Internship Cyber Security & SOC Analyst',
                'grade' => 'mahasiswa',
                'type' => 'full_time',
                'location' => 'onsite',
                'qouta' => 2,
                'stipend' => 'Rp 3.500.000 - Rp 4.500.000 / bulan',
                'desc' => 'Dapatkan pengalaman berharga dalam pemantauan keamanan jaringan, penetrasi sistem, dan analisis ancaman siber.'
            ],
            [
                'company_name' => 'PT. Solusi Indonesia Digital',
                'field_name' => 'UI/UX Design',
                'duration_months' => 3,
                'title' => 'Internship Product Designer (Figma)',
                'grade' => 'all',
                'type' => 'part_time',
                'location' => 'remote',
                'qouta' => 3,
                'stipend' => 'Rp 1.500.000 - Rp 2.000.000 / bulan',
                'desc' => 'Rancang wireframe, user flow, dan prototype produk digital modern menggunakan Figma untuk aplikasi e-commerce.'
            ],
            [
                'company_name' => 'PT. Maju Jaya Teknologi',
                'field_name' => 'Graphic Design',
                'duration_months' => 3,
                'title' => 'Internship Graphic & Motion Designer',
                'grade' => 'smk',
                'type' => 'full_time',
                'location' => 'hybrid',
                'qouta' => 4,
                'stipend' => 'Rp 1.200.000 - Rp 1.800.000 / bulan',
                'desc' => 'Buat visual branding yang memukau, konten animasi pendek, dan materi promosi digital menarik.'
            ],
            [
                'company_name' => 'PT. Bank Rakyat Indonesia (Persero) Tbk',
                'field_name' => 'Artificial Intelligence',
                'duration_months' => 6,
                'title' => 'Internship Machine Learning & AI Specialist',
                'grade' => 'mahasiswa',
                'type' => 'full_time',
                'location' => 'hybrid',
                'qouta' => 3,
                'stipend' => 'Rp 3.500.000 - Rp 5.000.000 / bulan',
                'desc' => 'Kembangkan model pemrosesan bahasa alami (NLP) dan Computer Vision untuk analisis data nasabah dan otomasi layanan BRI.'
            ],
            [
                'company_name' => 'PT. GoTo Gojek Tokopedia Tbk',
                'field_name' => 'Product Management',
                'duration_months' => 6,
                'title' => 'Internship Associate Product Manager',
                'grade' => 'mahasiswa',
                'type' => 'full_time',
                'location' => 'hybrid',
                'qouta' => 3,
                'stipend' => 'Rp 3.500.000 - Rp 5.000.000 / bulan',
                'desc' => 'Bekerja bersama Senior Product Manager dalam menyusun PRD, riset pengguna, dan strategi fitur baru ekosistem GoTo.'
            ],
        ];

        $createdCount = 0;
        foreach ($newJobs as $index => $j) {
            $company = $companies->get($j['company_name']);
            if (!$company) {
                $company = $companies->first();
            }

            $fieldId = $getFieldId($j['field_name']);
            $durationId = $getDurationId($j['duration_months']);

            // Avoid duplicate by title & company
            $existing = JobOpening::where('title', $j['title'])
                ->where('company_id', $company->id)
                ->first();

            if ($existing) {
                $existing->update([
                    'closing_date' => Carbon::now()->addDays(60)->toDateString(),
                    'is_available' => true,
                ]);
                continue;
            }

            $job = JobOpening::create([
                'company_id' => $company->id,
                'field_id' => $fieldId,
                'duration_id' => $durationId,
                'title' => $j['title'],
                'description' => [
                    'blocks' => [
                        [
                            'id' => 'seeder-' . $index . '-p1',
                            'type' => 'paragraph',
                            'data' => ['text' => $j['desc']]
                        ],
                        [
                            'id' => 'seeder-' . $index . '-h1',
                            'type' => 'header',
                            'data' => ['text' => 'Fasilitas & Uang Saku:', 'level' => 3]
                        ],
                        [
                            'id' => 'seeder-' . $index . '-l1',
                            'type' => 'list',
                            'data' => [
                                'style' => 'unordered',
                                'items' => [
                                    ['content' => 'Uang Saku: ' . $j['stipend']],
                                    ['content' => 'Sertifikat Magang Resmi Terverifikasi.'],
                                    ['content' => 'Mentorship eksklusif bersama praktisi industri.'],
                                    ['content' => 'Peluang diangkat menjadi karyawan tetap.'],
                                ]
                            ]
                        ]
                    ]
                ],
                'grade' => $j['grade'],
                'type' => $j['type'],
                'location' => $j['location'],
                'qouta' => $j['qouta'],
                'is_paid' => true,
                'is_available' => true,
                'start_date' => Carbon::now()->addDays(14)->toDateString(),
                'end_date' => Carbon::now()->addDays(14)->addMonths($j['duration_months'])->toDateString(),
                'closing_date' => Carbon::now()->addDays(60)->toDateString(),
            ]);

            // Assign existing tests for company if any
            $companyTests = Test::where('company_id', $company->id)->get();
            foreach ($companyTests as $test) {
                DB::table('job_opening_test')->insertOrIgnore([
                    'job_opening_id' => $job->id,
                    'test_id' => $test->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            $createdCount++;
        }

        echo "JobOpeningSeeder completed successfully: Updated existing closing dates and added {$createdCount} new active job openings.\n";
    }
}

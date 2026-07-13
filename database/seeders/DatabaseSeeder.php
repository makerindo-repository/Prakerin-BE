<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\CityRegency;
use App\Models\Sector;
use App\Models\Major;
use App\Models\Field;
use App\Models\Duration;
use App\Models\JobPosition;
use App\Models\User;
use App\Models\School;
use App\Models\Company;
use App\Models\Student;
use App\Models\CurriculumVitae;
use App\Models\Test;
use App\Models\JobOpening;
use App\Models\InternshipApplication;
use App\Models\SaveJobOpening;
use App\Models\Internship;
use App\Models\Task;
use App\Models\ReportTask;
use App\Models\ReportTaskMessage;
use App\Models\Certificate;
use App\Models\Feedback;
use App\Models\ContactUs;
use App\Models\CommentPrakerin;
use App\Models\Partner;
use App\Models\Hompage;
use App\Models\Mou;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Disable foreign key constraints and clean all tables
        Schema::disableForeignKeyConstraints();
        
        DB::table('comment_prakerins')->truncate();
        DB::table('partners')->truncate();
        DB::table('generated_cvs')->truncate();
        DB::table('user_user')->truncate();
        DB::table('feedback')->truncate();
        DB::table('contact_us')->truncate();
        DB::table('report_task_messages')->truncate();
        DB::table('report_tasks')->truncate();
        DB::table('certificates')->truncate();
        DB::table('tasks')->truncate();
        DB::table('internship_test')->truncate();
        DB::table('internships')->truncate();
        DB::table('save_job_openings')->truncate();
        DB::table('internship_application_test')->truncate();
        DB::table('internship_applications')->truncate();
        DB::table('curriculum_vitaes')->truncate();
        DB::table('job_opening_test')->truncate();
        DB::table('job_openings')->truncate();
        DB::table('tests')->truncate();
        DB::table('mous')->truncate();
        DB::table('students')->truncate();
        DB::table('companies')->truncate();
        DB::table('schools')->truncate();
        DB::table('job_positions')->truncate();
        DB::table('durations')->truncate();
        DB::table('fields')->truncate();
        DB::table('majors')->truncate();
        DB::table('sectors')->truncate();
        DB::table('city_regencies')->truncate();
        DB::table('provinces')->truncate();
        DB::table('hompages')->truncate();
        DB::table('users')->delete();

        Schema::enableForeignKeyConstraints();

        // 2. Call Spatie Roles and Permissions Seeder
        $this->call(RolePermissionSeeder::class);

        // 3. Seed Geolocation (Provinces and City Regencies)
        $provincesData = [
            ['name' => 'Jawa Barat', 'is_accepted' => true],
            ['name' => 'DKI Jakarta', 'is_accepted' => true],
            ['name' => 'Jawa Tengah', 'is_accepted' => true],
            ['name' => 'Jawa Timur', 'is_accepted' => true],
            ['name' => 'Banten', 'is_accepted' => true],
        ];

        $provinces = [];
        foreach ($provincesData as $prov) {
            $provinces[$prov['name']] = Province::create($prov);
        }

        $citiesData = [
            ['province_id' => $provinces['Jawa Barat']->id, 'name' => 'Kota Bandung', 'is_accepted' => true],
            ['province_id' => $provinces['Jawa Barat']->id, 'name' => 'Kota Bogor', 'is_accepted' => true],
            ['province_id' => $provinces['Jawa Barat']->id, 'name' => 'Kota Sukabumi', 'is_accepted' => true],
            ['province_id' => $provinces['Jawa Barat']->id, 'name' => 'Kota Banjar', 'is_accepted' => true],
            ['province_id' => $provinces['DKI Jakarta']->id, 'name' => 'Jakarta Selatan', 'is_accepted' => true],
            ['province_id' => $provinces['DKI Jakarta']->id, 'name' => 'Jakarta Pusat', 'is_accepted' => true],
            ['province_id' => $provinces['Jawa Tengah']->id, 'name' => 'Kota Semarang', 'is_accepted' => true],
            ['province_id' => $provinces['Jawa Timur']->id, 'name' => 'Kota Surabaya', 'is_accepted' => true],
            ['province_id' => $provinces['Banten']->id, 'name' => 'Kota Tangerang', 'is_accepted' => true],
        ];

        $cities = [];
        foreach ($citiesData as $city) {
            $cities[$city['name']] = CityRegency::create($city);
        }

        // 4. Seed Sectors, Majors, Fields, Durations, and Job Positions
        $sectorsData = [
            ['name' => 'Teknologi Informasi dan Komunikasi', 'is_accepted' => true],
            ['name' => 'Pendidikan dan Pelatihan', 'is_accepted' => true],
            ['name' => 'Finansial dan Perbankan', 'is_accepted' => true],
            ['name' => 'Industri Kreatif dan Desain', 'is_accepted' => true],
            ['name' => 'Manufaktur dan Elektronik', 'is_accepted' => true],
        ];
        $sectors = [];
        foreach ($sectorsData as $sect) {
            $sectors[$sect['name']] = Sector::create($sect);
        }

        $majorsData = [
            ['name' => 'Rekayasa Perangkat Lunak', 'level' => 'smk', 'is_accepted' => true],
            ['name' => 'Teknik Komputer dan Jaringan', 'level' => 'smk', 'is_accepted' => true],
            ['name' => 'Multimedia', 'level' => 'smk', 'is_accepted' => true],
            ['name' => 'Teknik Informatika', 'level' => 'college', 'is_accepted' => true],
            ['name' => 'Sistem Informasi', 'level' => 'college', 'is_accepted' => true],
            ['name' => 'Desain Komunikasi Visual', 'level' => 'college', 'is_accepted' => true],
        ];
        $majors = [];
        foreach ($majorsData as $maj) {
            $majors[$maj['name']] = Major::create($maj);
        }

        $fieldsData = [
            ['name' => 'Web Development', 'is_accepted' => true],
            ['name' => 'Mobile App Development', 'is_accepted' => true],
            ['name' => 'UI/UX Design', 'is_accepted' => true],
            ['name' => 'Digital Marketing', 'is_accepted' => true],
            ['name' => 'Data Analysis', 'is_accepted' => true],
            ['name' => 'Network & Security', 'is_accepted' => true],
        ];
        $fields = [];
        foreach ($fieldsData as $fld) {
            $fields[$fld['name']] = Field::create($fld);
        }

        $durationsData = [
            ['duration_value' => 3, 'duration_unit' => 'month', 'is_accepted' => true],
            ['duration_value' => 6, 'duration_unit' => 'month', 'is_accepted' => true],
            ['duration_value' => 9, 'duration_unit' => 'month', 'is_accepted' => true],
            ['duration_value' => 12, 'duration_unit' => 'month', 'is_accepted' => true],
        ];
        $durations = [];
        foreach ($durationsData as $dur) {
            $key = $dur['duration_value'] . ' ' . $dur['duration_unit'];
            $durations[$key] = Duration::create($dur);
        }

        $jobPositionsData = [
            ['name' => 'Web Developer', 'is_accepted' => true],
            ['name' => 'Mobile Developer', 'is_accepted' => true],
            ['name' => 'UI/UX Designer', 'is_accepted' => true],
            ['name' => 'Data Analyst', 'is_accepted' => true],
            ['name' => 'IT Support Specialist', 'is_accepted' => true],
            ['name' => 'Digital Marketer', 'is_accepted' => true],
            ['name' => 'Network Engineer', 'is_accepted' => true],
        ];
        $jobPositions = [];
        foreach ($jobPositionsData as $pos) {
            $jobPositions[$pos['name']] = JobPosition::create($pos);
        }

        // 5. Seed Homepage content (used by landing pages)
        $homPage = [
            ['name' => 'title-landing-1', 'value' => 'Raih Pengalaman Nyata, Bangun Karier Impianmu!'],
            ['name' => 'desc-landing-1', 'value' => 'Temukan lowongan magang terbaik yang sesuai dengan minat dan keahlianmu. Mulai perjalanan profesionalmu hari ini bersama Prakerin.'],
            ['name' => 'title-landing-2', 'value' => 'Kenapa Harus Magang Melalui Prakerin?'],
            ['name' => 'subtitle-landing-2', 'value' => 'Prakerin hadir sebagai solusi terpercaya untuk menjembatani talenta muda dengan perusahaan berkualitas.'],
            ['name' => 'title-content-landing-2-1', 'value' => 'Magang Terverifikasi'],
            ['name' => 'desc-content-landing-2-1', 'value' => 'Semua lowongan magang di Prakerin sudah melalui proses verifikasi ketat dari tim internal kami.'],
            ['name' => 'icon-content-landing-2-1', 'value' => 'CheckCircle2'],
            ['name' => 'title-content-landing-2-2', 'value' => 'Pendampingan Profesional'],
            ['name' => 'desc-content-landing-2-2', 'value' => 'Prakerin mendampingi setiap langkahmu, mulai dari seleksi, pengerjaan tugas mingguan, hingga laporan akhir.'],
            ['name' => 'icon-content-landing-2-2', 'value' => 'Users2'],
            ['name' => 'title-content-landing-2-3', 'value' => 'Bangun Portofolio Nyata'],
            ['name' => 'desc-content-landing-2-3', 'value' => 'Dapatkan pengalaman kerja aktual di industri, asah keterampilan praktismu, dan perkuat CV profesionalmu.'],
            ['name' => 'icon-content-landing-2-3', 'value' => 'Inbox'],
            ['name' => 'title-landing-3', 'value' => 'Mitra Perusahaan Terpercaya'],
            ['name' => 'subtitle-landing-3', 'value' => 'Wujudkan magang di perusahaan impian Anda bersama para mitra industri resmi Prakerin.'],
            ['name' => 'title-landing-4', 'value' => 'Success Story'],
            ['name' => 'subtitle-landing-4', 'value' => 'Apa kata mereka yang telah sukses menyelesaikan program magang melalui platform Prakerin?'],
            ['name' => 'title-landing-5', 'value' => "Let's Grow Together!"],
            ['name' => 'subtitle-landing-5', 'value' => 'Mulai wujudkan impianmu! Prakerin siap mendukung penuh langkah awal perjalanan kariermu.'],
            ['name' => 'title-landing-6', 'value' => 'Pertanyaan yang Sering Diajukan'],
            ['name' => 'subtitle-landing-6', 'value' => 'Berikut adalah beberapa hal yang sering ditanyakan mengenai alur pendaftaran dan pelaksanaan magang.'],
            ['name' => 'title-landing-7', 'value' => 'Hubungi Kami'],
            ['name' => 'subtitle-landing-7', 'value' => 'Punya ide kolaborasi, pertanyaan seputar kemitraan, atau kendala platform? Kami siap membantu Anda kapan saja.'],
            ['name' => 'title-about-1', 'value' => 'Membangun Jembatan Talenta Indonesia'],
            ['name' => 'desc-about-1', 'value' => 'Prakerin adalah platform digital inovatif yang didedikasikan untuk menghubungkan siswa SMK dan mahasiswa perguruan tinggi di seluruh Indonesia dengan kesempatan magang berkualitas. Kami percaya bahwa pengalaman kerja nyata adalah kunci utama dalam membangun kesiapan kerja generasi muda Indonesia.'],
        ];

        foreach ($homPage as $item) {
            Hompage::create($item);
        }

        // 6. Seed Users, Spatie Roles, and Profiles
        // A. Super Admin
        $superAdminUser = User::create([
            'username' => 'superadmin',
            'email' => 'superadmin@makerindo.id',
            'password' => Hash::make('rahasia123'),
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
        $superAdminUser->assignRole('super_admin');

        // B. Schools (2 schools: 1 SMK, 1 University)
        $school1User = User::create([
            'username' => 'smkn1bandung',
            'email' => 'smkn1bdg@school.id',
            'password' => Hash::make('rahasia123'),
            'role' => 'school',
            'email_verified_at' => now(),
            'photo_profile' => 'pfpupload/univlogo1.jpeg',
        ]);
        $school1User->assignRole('school_admin');
        
        $school1 = School::create([
            'user_id' => $school1User->id,
            'city_regency_id' => $cities['Kota Bandung']->id,
            'name' => 'SMK Negeri 1 Bandung',
            'type' => 'school',
            'address' => 'Jl. Wastukencana No.3, Babakan Ciamis, Kec. Sumur Bandung, Kota Bandung, Jawa Barat 40117',
            'description' => [
                'blocks' => [
                    [
                        'id' => 'sch1-desc',
                        'type' => 'paragraph',
                        'data' => ['text' => 'Sekolah Menengah Kejuruan Negeri unggulan di Kota Bandung yang berfokus pada pengembangan keahlian teknologi dan rekayasa perangkat lunak.']
                    ]
                ]
            ],
            'phone_number' => '+62224204562',
            'is_verified' => true,
            'accreditation' => 'A',
            'npsn' => '20219123',
            'status' => 'negeri',
            'website' => 'https://smkn1bandung.sch.id',
        ]);

        $school2User = User::create([
            'username' => 'univ_indonesia',
            'email' => 'ui@university.id',
            'password' => Hash::make('rahasia123'),
            'role' => 'school',
            'email_verified_at' => now(),
            'photo_profile' => 'pfpupload/univlogo2.jpeg',
        ]);
        $school2User->assignRole('university_admin');

        $school2 = School::create([
            'user_id' => $school2User->id,
            'city_regency_id' => $cities['Jakarta Selatan']->id,
            'name' => 'Universitas Indonesia',
            'type' => 'university',
            'address' => 'Kampus UI Depok, Kec. Beji, Kota Depok, Jawa Barat 16424',
            'description' => [
                'blocks' => [
                    [
                        'id' => 'sch2-desc',
                        'type' => 'paragraph',
                        'data' => ['text' => 'Perguruan tinggi negeri terkemuka di Indonesia yang berkomitmen melahirkan talenta profesional siap bersaing di kancah global.']
                    ]
                ]
            ],
            'phone_number' => '+62217867222',
            'is_verified' => true,
            'accreditation' => 'A',
            'npsn' => '001002',
            'status' => 'negeri',
            'website' => 'https://ui.ac.id',
        ]);

        // C. Companies (6 companies)
        $companiesData = [
            [
                'username' => 'makerindo', 'email' => 'makerindo@company.com', 'name' => 'PT. Makerindo Prima Solusi',
                'address' => 'Jl. Sentra Dago Pakar Raya No.21, Bandung', 'city' => 'Kota Bandung', 'sector' => 'Teknologi Informasi dan Komunikasi',
                'photo_profile' => 'pfpupload/corplogo1.jpeg'
            ],
            [
                'username' => 'telkom', 'email' => 'telkom@company.com', 'name' => 'PT. Telkom Indonesia (Persero) Tbk',
                'address' => 'Telkom Landmark Tower, Jl. Jend. Gatot Subroto No.52, Jakarta Selatan', 'city' => 'Jakarta Selatan', 'sector' => 'Teknologi Informasi dan Komunikasi',
                'photo_profile' => 'pfpupload/corplogo2.png'
            ],
            [
                'username' => 'solusidigital', 'email' => 'solusidigital@company.com', 'name' => 'PT. Solusi Indonesia Digital',
                'address' => 'Gedung Cyber 2 Lantai 15, Jl. H. R. Rasuna Said, Jakarta Selatan', 'city' => 'Jakarta Selatan', 'sector' => 'Teknologi Informasi dan Komunikasi',
                'photo_profile' => 'pfpupload/corplogo3.png'
            ],
            [
                'username' => 'majujaya', 'email' => 'majujaya@company.com', 'name' => 'PT. Maju Jaya Teknologi',
                'address' => 'Jl. Asia Afrika No.141-143, Bandung', 'city' => 'Kota Bandung', 'sector' => 'Industri Kreatif dan Desain',
                'photo_profile' => 'pfpupload/corplogo4.png'
            ],
            [
                'username' => 'bri', 'email' => 'bri@company.com', 'name' => 'PT. Bank Rakyat Indonesia (Persero) Tbk',
                'address' => 'Gedung BRI 1, Jl. Jend. Sudirman Kav.44-46, Jakarta Pusat', 'city' => 'Jakarta Pusat', 'sector' => 'Finansial dan Perbankan',
                'photo_profile' => 'pfpupload/corplogo1.jpeg'
            ],
            [
                'username' => 'goto', 'email' => 'goto@company.com', 'name' => 'PT. GoTo Gojek Tokopedia Tbk',
                'address' => 'Pasaraya Blok M Gedung B Lt. 6, Jl. Iskandarsyah II No.7, Jakarta Selatan', 'city' => 'Jakarta Selatan', 'sector' => 'Teknologi Informasi dan Komunikasi',
                'photo_profile' => 'pfpupload/corplogo2.png'
            ]
        ];

        $companies = [];
        foreach ($companiesData as $cData) {
            $user = User::create([
                'username' => $cData['username'],
                'email' => $cData['email'],
                'password' => Hash::make('rahasia123'),
                'role' => 'company',
                'email_verified_at' => now(),
                'photo_profile' => $cData['photo_profile'],
            ]);
            $user->assignRole('company_owner');

            $comp = Company::create([
                'user_id' => $user->id,
                'city_regency_id' => $cities[$cData['city']]->id,
                'sector_id' => $sectors[$cData['sector']]->id,
                'name' => $cData['name'],
                'address' => $cData['address'],
                'description' => [
                    'blocks' => [
                        [
                            'id' => 'comp-' . $cData['username'] . '-desc',
                            'type' => 'paragraph',
                            'data' => ['text' => 'Perusahaan terkemuka yang bergerak dalam penyediaan solusi berskala nasional serta berkomitmen mengembangkan ekosistem digital Indonesia.']
                        ]
                    ]
                ],
                'phone_number' => '+6281' . rand(11111111, 99999999),
                'is_verified' => true,
                'website' => 'https://' . str_replace(' ', '', strtolower(explode('.', $cData['name'])[1] ?? $cData['username'])) . '.co.id',
            ]);
            $companies[$cData['name']] = $comp;
        }

        // D. Students (11 students: 5 SMK, 6 University)
        $studentsData = [
            // 5 SMK students
            ['username' => 'budi.santoso', 'email' => 'budi@student.com', 'name' => 'Budi Santoso', 'school' => 'school1', 'major' => 'Rekayasa Perangkat Lunak', 'class' => '12', 'gender' => 'male', 'photo_profile' => 'pfpupload/usrpfp1.jpeg'],
            ['username' => 'ani.lestari', 'email' => 'ani@student.com', 'name' => 'Ani Lestari', 'school' => 'school1', 'major' => 'Rekayasa Perangkat Lunak', 'class' => '12', 'gender' => 'female', 'photo_profile' => 'pfpupload/usrpfp2.jpeg'],
            ['username' => 'joko.susilo', 'email' => 'joko@student.com', 'name' => 'Joko Susilo', 'school' => 'school1', 'major' => 'Teknik Komputer dan Jaringan', 'class' => '11', 'gender' => 'male', 'photo_profile' => 'pfpupload/usrpfp3.jpeg'],
            ['username' => 'siti.aminah', 'email' => 'siti@student.com', 'name' => 'Siti Aminah', 'school' => 'school1', 'major' => 'Multimedia', 'class' => '12', 'gender' => 'female', 'photo_profile' => 'pfpupload/usrpfp5.jpeg'],
            ['username' => 'dodi.hidayat', 'email' => 'dodi@student.com', 'name' => 'Dodi Hidayat', 'school' => 'school1', 'major' => 'Rekayasa Perangkat Lunak', 'class' => '11', 'gender' => 'male', 'photo_profile' => 'pfpupload/usrpfp6.jpeg'],
            
            // 6 University students
            ['username' => 'eka.wijaya', 'email' => 'eka@student.com', 'name' => 'Eka Wijaya', 'school' => 'school2', 'major' => 'Teknik Informatika', 'class' => '5', 'gender' => 'male', 'photo_profile' => 'pfpupload/usrpfp4.jpeg'],
            ['username' => 'fitri.astuti', 'email' => 'fitri@student.com', 'name' => 'Fitri Astuti', 'school' => 'school2', 'major' => 'Sistem Informasi', 'class' => '6', 'gender' => 'female', 'photo_profile' => 'pfpupload/usrpfp5.jpeg'],
            ['username' => 'guntur.pratama', 'email' => 'guntur@student.com', 'name' => 'Guntur Pratama', 'school' => 'school2', 'major' => 'Desain Komunikasi Visual', 'class' => '7', 'gender' => 'male', 'photo_profile' => 'pfpupload/usrpfp6.jpeg'],
            ['username' => 'harta.wiguna', 'email' => 'harta@student.com', 'name' => 'Harta Wiguna', 'school' => 'school2', 'major' => 'Teknik Informatika', 'class' => '8', 'gender' => 'male', 'photo_profile' => 'pfpupload/usrpfp1.jpeg'],
            ['username' => 'indah.permata', 'email' => 'indah@student.com', 'name' => 'Indah Permata', 'school' => 'school2', 'major' => 'Sistem Informasi', 'class' => '5', 'gender' => 'female', 'photo_profile' => 'pfpupload/usrpfp2.jpeg'],
            ['username' => 'tomi.saputra', 'email' => 'tomi@student.com', 'name' => 'Tomi Saputra', 'school' => 'school2', 'major' => 'Teknik Informatika', 'class' => '6', 'gender' => 'male', 'photo_profile' => 'pfpupload/usrpfp3.jpeg'],
        ];

        $students = [];
        foreach ($studentsData as $sData) {
            $user = User::create([
                'username' => $sData['username'],
                'email' => $sData['email'],
                'password' => Hash::make('rahasia123'),
                'role' => 'student',
                'email_verified_at' => now(),
                'photo_profile' => $sData['photo_profile'],
            ]);

            $isUniversity = ($sData['school'] === 'school2');
            $spatieRole = $isUniversity ? 'mahasiswa' : 'siswa';
            $user->assignRole($spatieRole);

            $schoolId = $sData['school'] === 'school1' ? $school1->id : $school2->id;

            $stud = Student::create([
                'user_id' => $user->id,
                'school_id' => $schoolId,
                'major_id' => $majors[$sData['major']]->id,
                'name' => $sData['name'],
                'status' => 'not_started',
                'date_of_birth' => Carbon::now()->subYears($isUniversity ? 21 : 17)->toDateString(),
                'gender' => $sData['gender'],
                'phone_number' => '+6285' . rand(11111111, 99999999),
                'address' => 'Jl. Raya Pendidikan No. ' . rand(1, 100) . ', ' . ($sData['school'] === 'school1' ? 'Bandung' : 'Depok'),
                'is_verified' => true,
                'class' => $sData['class'],
                'skill' => $sData['major'] . ', Git, Teamwork',
                'portofolio_link' => 'https://github.com/' . $sData['username'],
                'social_media_link' => 'https://linkedin.com/in/' . $sData['username'],
            ]);
            $students[$sData['name']] = $stud;
        }

        // 7. Seed Curriculum Vitaes (CVs) for each student
        $cvs = [];
        foreach ($students as $name => $student) {
            $cv = CurriculumVitae::create([
                'student_id' => $student->id,
                'name' => 'CV_' . str_replace(' ', '_', $name) . '.pdf',
                'file' => 'cvs/cv_' . str_replace(' ', '_', strtolower($name)) . '.pdf',
            ]);
            $cvs[$name] = $cv;
        }

        // 8. Seed Tests for Companies
        $tests = [];
        foreach ($companies as $name => $company) {
            $test1 = Test::create([
                'company_id' => $company->id,
                'title' => 'Tes Teori & Algoritma - ' . $company->name,
                'link' => 'https://coding-test.makerindo.id/theory/' . Str::slug($name),
                'description' => 'Evaluasi tertulis mengenai pemahaman logika pemrograman dasar, OOP, database design, dan penyelesaian masalah analitis.',
                'type' => 'theory',
            ]);

            $test2 = Test::create([
                'company_id' => $company->id,
                'title' => 'Tes Praktik Keahlian - ' . $company->name,
                'link' => 'https://coding-test.makerindo.id/practice/' . Str::slug($name),
                'description' => 'Tugas praktis membuat aplikasi kecil/dashboard mini sesuai dengan posisi yang dilamar menggunakan framework modern.',
                'type' => 'practice',
            ]);

            $tests[$name] = [$test1, $test2];
        }

        // 9. Seed Job Openings (Internship Postings)
        $jobsData = [
            [
                'company' => 'PT. Makerindo Prima Solusi', 'field' => 'Web Development', 'duration' => '3 month',
                'title' => 'Internship Backend Developer (Laravel)', 'grade' => 'all', 'type' => 'full_time', 'location' => 'hybrid',
                'stipend' => 'Rp 1.500.000 - Rp 2.000.000 / bulan'
            ],
            [
                'company' => 'PT. Makerindo Prima Solusi', 'field' => 'UI/UX Design', 'duration' => '3 month',
                'title' => 'Internship UI/UX Designer', 'grade' => 'all', 'type' => 'part_time', 'location' => 'remote',
                'stipend' => 'Rp 1.200.000 - Rp 1.800.000 / bulan'
            ],
            [
                'company' => 'PT. Telkom Indonesia (Persero) Tbk', 'field' => 'Network & Security', 'duration' => '6 month',
                'title' => 'Internship Network & Infrastructure Engineer', 'grade' => 'mahasiswa', 'type' => 'full_time', 'location' => 'onsite',
                'stipend' => 'Rp 2.500.000 - Rp 3.500.000 / bulan'
            ],
            [
                'company' => 'PT. GoTo Gojek Tokopedia Tbk', 'field' => 'Mobile App Development', 'duration' => '6 month',
                'title' => 'Internship React Native / Flutter Developer', 'grade' => 'mahasiswa', 'type' => 'full_time', 'location' => 'hybrid',
                'stipend' => 'Rp 3.000.000 - Rp 4.500.000 / bulan'
            ],
            [
                'company' => 'PT. Solusi Indonesia Digital', 'field' => 'Web Development', 'duration' => '3 month',
                'title' => 'Junior Web Developer (React & Tailwind)', 'grade' => 'smk', 'type' => 'full_time', 'location' => 'onsite',
                'stipend' => 'Rp 1.500.000 - Rp 2.200.000 / bulan'
            ],
            [
                'company' => 'PT. Solusi Indonesia Digital', 'field' => 'Data Analysis', 'duration' => '3 month',
                'title' => 'Internship Data Analyst', 'grade' => 'mahasiswa', 'type' => 'full_time', 'location' => 'remote',
                'stipend' => 'Rp 2.000.000 - Rp 2.800.000 / bulan'
            ],
            [
                'company' => 'PT. Maju Jaya Teknologi', 'field' => 'Digital Marketing', 'duration' => '3 month',
                'title' => 'Internship Content Creator & Social Media Specialist', 'grade' => 'smk', 'type' => 'part_time', 'location' => 'hybrid',
                'stipend' => 'Rp 1.000.000 - Rp 1.500.000 / bulan'
            ],
            [
                'company' => 'PT. Bank Rakyat Indonesia (Persero) Tbk', 'field' => 'Data Analysis', 'duration' => '6 month',
                'title' => 'Internship Data Engineer', 'grade' => 'mahasiswa', 'type' => 'full_time', 'location' => 'onsite',
                'stipend' => 'Rp 2.800.000 - Rp 3.800.000 / bulan'
            ],
        ];

        $jobOpenings = [];
        foreach ($jobsData as $index => $jData) {
            $company = $companies[$jData['company']];
            $field = $fields[$jData['field']];
            $duration = $durations[$jData['duration']];

            $job = JobOpening::create([
                'company_id' => $company->id,
                'field_id' => $field->id,
                'duration_id' => $duration->id,
                'title' => $jData['title'],
                'description' => [
                    'blocks' => [
                        [
                            'id' => 'job-' . $index . '-p1',
                            'type' => 'paragraph',
                            'data' => ['text' => 'Kami membuka kesempatan bagi talenta muda untuk bergabung dan belajar langsung dari tim engineering profesional kami dalam membangun sistem berskala nasional.']
                        ],
                        [
                            'id' => 'job-' . $index . '-h1',
                            'type' => 'header',
                            'data' => ['text' => 'Persyaratan / Kualifikasi:', 'level' => 3]
                        ],
                        [
                            'id' => 'job-' . $index . '-l1',
                            'type' => 'list',
                            'data' => [
                                'style' => 'unordered',
                                'items' => [
                                    ['content' => 'Kemampuan dasar dalam pemrograman sesuai bidang terkait.'],
                                    ['content' => 'Familiar dengan kontrol versi kode (Git/GitHub).'],
                                    ['content' => 'Memiliki dedikasi tinggi dan bersedia bekerja sama dalam tim.'],
                                    ['content' => 'Kompensasi Uang Saku: ' . $jData['stipend']],
                                ]
                            ]
                        ]
                    ]
                ],
                'grade' => $jData['grade'],
                'type' => $jData['type'],
                'location' => $jData['location'],
                'qouta' => rand(2, 5),
                'is_paid' => true,
                'is_available' => true,
                'start_date' => Carbon::now()->addDays(15)->toDateString(),
                'end_date' => Carbon::now()->addDays(15)->addMonths($duration->duration_value)->toDateString(),
                'closing_date' => Carbon::now()->addDays(10)->toDateString(),
            ]);

            // Link job opening with company's tests
            $companyTests = $tests[$jData['company']] ?? [];
            foreach ($companyTests as $test) {
                DB::table('job_opening_test')->insert([
                    'job_opening_id' => $job->id,
                    'test_id' => $test->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            $jobOpenings[$jData['title']] = $job;
        }

        // 10. Seed Internship Applications
        $applicationsData = [
            // Student Budi (SMK)
            ['student' => 'Budi Santoso', 'job' => 'Junior Web Developer (React & Tailwind)', 'status' => 'accepted', 'cover' => 'Saya tertarik melamar magang sebagai Junior Web Developer untuk mengaplikasikan ilmu pemrograman web (HTML, CSS, ReactJS) yang telah saya pelajari di SMKN 1 Bandung.'],
            ['student' => 'Budi Santoso', 'job' => 'Internship Content Creator & Social Media Specialist', 'status' => 'rejected', 'cover' => 'Saya memiliki minat besar dalam dunia digital branding dan media sosial.'],
            
            // Student Ani (SMK)
            ['student' => 'Ani Lestari', 'job' => 'Junior Web Developer (React & Tailwind)', 'status' => 'accepted', 'cover' => 'Melalui magang ini saya berharap dapat mengasah kemampuan ReactJS dan CSS modern saya di proyek nyata.'],
            
            // Student Joko (SMK)
            ['student' => 'Joko Susilo', 'job' => 'Junior Web Developer (React & Tailwind)', 'status' => 'in_progress', 'cover' => 'Saya Joko Susilo, memiliki ketertarikan kuat di bidang infrastruktur jaringan dan pengembangan web frontend.'],
            
            // Student Eka (University)
            ['student' => 'Eka Wijaya', 'job' => 'Internship Backend Developer (Laravel)', 'status' => 'accepted', 'cover' => 'Sebagai mahasiswa Teknik Informatika Universitas Indonesia, saya telah membangun beberapa proyek backend dengan Laravel dan ingin belajar lebih lanjut di lingkungan enterprise.'],
            
            // Student Fitri (University)
            ['student' => 'Fitri Astuti', 'job' => 'Internship Data Analyst', 'status' => 'accepted', 'cover' => 'Saya memiliki keahlian dalam analisis data menggunakan Python, SQL, dan visualisasi data Tableau.'],
            
            // Student Guntur (University)
            ['student' => 'Guntur Pratama', 'job' => 'Internship UI/UX Designer', 'status' => 'in_progress', 'cover' => 'Sebagai mahasiswa DKV, saya fokus pada desain antarmuka pengguna yang estetik, intuitif, dan fungsional.'],
            
            // Student Harta (University)
            ['student' => 'Harta Wiguna', 'job' => 'Internship React Native / Flutter Developer', 'status' => 'in_progress', 'cover' => 'Saya memiliki portofolio pengembangan aplikasi mobile menggunakan Flutter dan ingin memperdalamnya di GoTo.'],
            
            // Student Indah (University)
            ['student' => 'Indah Permata', 'job' => 'Internship Data Engineer', 'status' => 'in_progress', 'cover' => 'Saya ingin berkontribusi dalam perancangan arsitektur data berskala besar di BRI.'],
            
            // Student Tomi (University)
            ['student' => 'Tomi Saputra', 'job' => 'Internship Backend Developer (Laravel)', 'status' => 'in_progress', 'cover' => 'Saya menyukai pengembangan backend API, database optimasi, dan integrasi payment gateway.'],
        ];

        $applications = [];
        foreach ($applicationsData as $aData) {
            $student = $students[$aData['student']];
            $cv = $cvs[$aData['student']];
            $job = $jobOpenings[$aData['job']];

            $app = InternshipApplication::create([
                'curriculum_vitae_id' => $cv->id,
                'job_opening_id' => $job->id,
                'status' => $aData['status'],
                'cover_letter' => $aData['cover'],
                'message_rejected' => $aData['status'] === 'rejected' ? 'Kapasitas kuota posisi ini sudah terpenuhi untuk gelombang ini. Terima kasih telah mendaftar.' : null,
                'rating' => $aData['status'] === 'accepted' ? rand(4, 5) : null,
            ]);

            // Link to internship_application_test for selection tracking
            $companyName = Company::find($job->company_id)->name;
            $companyTests = $tests[$companyName] ?? [];
            foreach ($companyTests as $test) {
                DB::table('internship_application_test')->insert([
                    'internship_application_id' => $app->id,
                    'test_id' => $test->id,
                    'is_passed' => $aData['status'] === 'accepted' ? true : (rand(0, 1) == 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $applications[] = $app;

            // 11. Seed Save Job Openings (Bookmarks)
            if ($aData['status'] === 'in_progress') {
                SaveJobOpening::create([
                    'student_id' => $student->id,
                    'job_opening_id' => $job->id,
                ]);
            }
        }

        // 12. Seed Internships (Accepted Students)
        // 2 Completed internships (so we can show successful statistics/certificates/ratings)
        // 2 Ongoing internships
        $acceptedApps = InternshipApplication::where('status', 'accepted')->get();
        $internships = [];

        foreach ($acceptedApps as $index => $app) {
            $jobOpening = JobOpening::find($app->job_opening_id);
            $studentName = null;
            foreach ($cvs as $name => $cv) {
                if ($cv->id === $app->curriculum_vitae_id) {
                    $studentName = $name;
                    break;
                }
            }
            $student = $students[$studentName] ?? Student::inRandomOrder()->first();
            $company = Company::find($jobOpening->company_id);
            
            $isCompleted = ($index < 2); // First 2 are completed
            $startDate = $isCompleted ? Carbon::now()->subMonths(4) : Carbon::now()->subMonth();
            $endDate = $isCompleted ? Carbon::now()->subMonths(1) : Carbon::now()->addMonths(2);
            
            $posName = $isCompleted ? 'Web Developer' : ($index == 2 ? 'UI/UX Designer' : 'Data Analyst');
            $jobPosition = $jobPositions[$posName] ?? JobPosition::inRandomOrder()->first();

            $intern = Internship::create([
                'internship_application_id' => $app->id,
                'job_position_id' => $jobPosition->id,
                'student_id' => $student->id,
                'company_id' => $company->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'is_completed' => $isCompleted,
            ]);

            // Update student status
            $student->update(['status' => $isCompleted ? 'completed' : 'ongoing']);

            // Link tests to active internships
            $companyTests = $tests[$company->name] ?? [];
            foreach ($companyTests as $test) {
                DB::table('internship_test')->insert([
                    'internship_id' => $intern->id,
                    'test_id' => $test->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $internships[] = $intern;

            // 13. Seed Tasks (Only for Ongoing internships)
            if (!$isCompleted) {
                $tasksData = [
                    ['title' => 'Perancangan Struktur Database & REST API Specs', 'desc' => 'Buatlah dokumentasi skema database relasional beserta spesifikasi API endpoint menggunakan Postman/Swagger.', 'status' => 'completed', 'days_offset' => -20],
                    ['title' => 'Slicing UI Dashboard Antarmuka Pengguna', 'desc' => 'Implementasikan desain dashboard administrasi berdasarkan Figma mockups menggunakan React & TailwindCSS secara responsif.', 'status' => 'in_progress', 'days_offset' => -5],
                    ['title' => 'Integrasi API & Autentikasi Pengguna', 'desc' => 'Hubungkan komponen frontend yang telah dibuat dengan REST API Backend menggunakan JWT/Sanctum Auth.', 'status' => 'pending', 'days_offset' => 5],
                ];

                foreach ($tasksData as $tData) {
                    $task = Task::create([
                        'internship_id' => $intern->id,
                        'title' => $tData['title'],
                        'description' => $tData['desc'],
                        'status' => $tData['status'],
                        'due_date' => Carbon::now()->addDays($tData['days_offset'])->toDateString(),
                        'link' => $tData['status'] === 'completed' ? 'https://github.com/internship/project-task' : null,
                    ]);

                    // 13B. Seed Reports & Conversation Thread for Completed/In-Progress Tasks
                    if ($tData['status'] !== 'pending') {
                        $reportTask = ReportTask::create([
                            'task_id' => $task->id,
                        ]);

                        // Messages thread
                        ReportTaskMessage::create([
                            'report_task_id' => $reportTask->id,
                            'student_id' => $student->id,
                            'message' => 'Selamat siang mentor, saya telah menyelesaikan tugas "' . $tData['title'] . '". Berikut adalah tautan repositori pekerjaan saya: ' . ($task->link ?? 'https://github.com/internship/project-task') . '. Mohon feedback-nya.',
                        ]);

                        if ($tData['status'] === 'completed') {
                            ReportTaskMessage::create([
                                'report_task_id' => $reportTask->id,
                                'company_id' => $company->id,
                                'message' => 'Terima kasih atas laporannya. Hasil pekerjaan Anda sudah sangat baik, kode terstruktur dengan rapi. Tugas ini saya tandai Selesai. Silakan lanjut ke tugas berikutnya.',
                            ]);
                        } else {
                            ReportTaskMessage::create([
                                'report_task_id' => $reportTask->id,
                                'company_id' => $company->id,
                                'message' => 'Laporan diterima. Ada sedikit revisi pada bagian desain responsif di layar mobile. Perbaiki layout grid agar tidak terpotong, lalu infokan kembali jika sudah selesai.',
                            ]);
                        }
                    }
                }
            }

            // 14. Seed Certificates and Feedback (Only for Completed internships)
            if ($isCompleted) {
                // Seed Certificate
                Certificate::create([
                    'internship_id' => $intern->id,
                ]);

                // Seed Feedback (Student to Company, Company to Student)
                $studentUser = User::find($student->user_id);
                $companyUser = User::find($company->user_id);

                if ($studentUser && $companyUser) {
                    // Feedback from Student to Company
                    Feedback::create([
                        'from_user_id' => $studentUser->id,
                        'to_user_id' => $companyUser->id,
                        'to_type' => 'company',
                        'rating' => 5,
                        'text' => 'Magang di ' . $company->name . ' sangat menyenangkan! Mentor sangat suportif, selalu meluangkan waktu untuk berdiskusi, dan proyek yang dikerjakan sangat menambah wawasan teknologi saya.',
                    ]);

                    // Feedback from Company to Student
                    Feedback::create([
                        'from_user_id' => $companyUser->id,
                        'to_user_id' => $studentUser->id,
                        'to_type' => 'student',
                        'rating' => 5,
                        'text' => $student->name . ' memiliki etos kerja yang luar biasa. Sangat proaktif, disiplin waktu, dan memiliki logika pemrograman yang matang. Sukses selalu kariernya!',
                    ]);

                    // Seed relationship link
                    DB::table('user_user')->insert([
                        'user_id' => $studentUser->id,
                        'related_user_id' => $companyUser->id,
                        'is_done' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 15. Seed Comment Prakerins (Testimonials on Landing Page)
        $testimonials = [
            [
                'photo_profile' => 'pfpupload/usrpfp1.jpeg',
                'name' => 'Budi Santoso',
                'position' => 'Alumni Magang SMK Negeri 1 Bandung',
                'comment' => 'Magang melalui Prakerin membuka lebar jalan karier saya. Setelah menyelesaikan magang 3 bulan di PT. Makerindo Prima Solusi, saya langsung ditawari kontrak kerja menjadi Junior Web Developer. Pengalaman kerjanya nyata dan aplikatif!',
            ],
            [
                'photo_profile' => 'pfpupload/usrpfp4.jpeg',
                'name' => 'Eka Wijaya',
                'position' => 'Alumni Magang Universitas Indonesia',
                'comment' => 'Platform Prakerin sangat mempermudah pencarian perusahaan yang terverifikasi dan bereputasi baik. Pendampingan administrasi sekolah serta penilaian tugas mingguan membuat magang saya terasa terstruktur dan profesional.',
            ],
            [
                'photo_profile' => 'pfpupload/usrpfp3.jpeg',
                'name' => 'Dewi Lestari',
                'position' => 'Alumni Magang SMKS Wikrama Bogor',
                'comment' => 'Fitur dashboard pemantauan tugas yang disediakan platform Prakerin sangat user-friendly. Membantu saya menyusun rencana kerja harian dengan rapi dan mempercepat komunikasi dengan pihak industri.',
            ],
            [
                'photo_profile' => 'pfpupload/usrpfp5.jpeg',
                'name' => 'Fitri Astuti',
                'position' => 'Alumni Magang Universitas Indonesia',
                'comment' => 'Sangat bersyukur dengan adanya seleksi tes teknis yang terintegrasi di Prakerin. Soal-soalnya sangat relevan dengan kebutuhan industri sesungguhnya, sehingga melatih kesiapan mental kerja saya semenjak kuliah.',
            ],
        ];

        foreach ($testimonials as $testi) {
            CommentPrakerin::create($testi);
        }

        // 16. Seed MOUs (Partnership Agreements)
        Mou::create([
            'company_id' => $companies['PT. Makerindo Prima Solusi']->id,
            'school_id' => $school1->id,
            'message' => 'Kerjasama Program Praktek Kerja Industri (Prakerin) untuk Bidang Rekayasa Perangkat Lunak gelombang ajaran tahun 2026/2027.',
            'file' => 'mous/mou_makerindo_smkn1bdg.pdf',
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'end_date' => Carbon::now()->addYears(2)->toDateString(),
            'status' => 'accepted',
            'is_company_accepted' => true,
            'is_school_accepted' => true,
        ]);

        Mou::create([
            'company_id' => $companies['PT. Telkom Indonesia (Persero) Tbk']->id,
            'school_id' => $school2->id,
            'message' => 'Program Kerjasama Magang Bersertifikat Industri Digital untuk mahasiswa Fakultas Ilmu Komputer Universitas Indonesia.',
            'file' => 'mous/mou_telkom_ui.pdf',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addYears(3)->toDateString(),
            'status' => 'accepted',
            'is_company_accepted' => true,
            'is_school_accepted' => true,
        ]);

        Mou::create([
            'company_id' => $companies['PT. Solusi Indonesia Digital']->id,
            'school_id' => $school1->id,
            'message' => 'Pengajuan kerjasama rekruitmen lulusan SMK dan program praktek kerja industri di lingkungan perkantoran regional Jawa Barat.',
            'file' => 'mous/mou_solusidigital_smkn1bdg.pdf',
            'start_date' => Carbon::now()->addMonths(1)->toDateString(),
            'end_date' => Carbon::now()->addYears(1)->toDateString(),
            'status' => 'pending',
            'is_company_accepted' => null,
            'is_school_accepted' => true,
        ]);

        // 17. Seed Partners (Rendered on Home Logos)
        $partnersData = [
            ['name' => 'SMK Negeri 1 Bandung', 'logo' => 'pfpupload/univlogo1.jpeg', 'address' => 'Bandung', 'type' => 'school'],
            ['name' => 'Universitas Indonesia', 'logo' => 'pfpupload/univlogo2.jpeg', 'address' => 'Depok', 'type' => 'university'],
            ['name' => 'PT. Makerindo Prima Solusi', 'logo' => 'pfpupload/corplogo1.jpeg', 'address' => 'Bandung', 'type' => 'company'],
            ['name' => 'PT. Telkom Indonesia', 'logo' => 'pfpupload/corplogo2.png', 'address' => 'Jakarta', 'type' => 'company'],
            ['name' => 'PT. GoTo Gojek Tokopedia', 'logo' => 'pfpupload/corplogo3.png', 'address' => 'Jakarta', 'type' => 'company'],
            ['name' => 'SMKS Wikrama Bogor', 'logo' => 'pfpupload/univlogo3.png', 'address' => 'Bogor', 'type' => 'school'],
        ];

        foreach ($partnersData as $part) {
            Partner::create($part);
        }

        // 18. Seed Contact Us messages
        ContactUs::create([
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmadfauzi@gmail.com',
            'message' => 'Halo tim Prakerin, saya ingin berkonsultasi mengenai bagaimana cara mendaftarkan sekolah kami menjadi mitra resmi Prakerin. Terimakasih.',
            'is_read' => true,
        ]);

        ContactUs::create([
            'name' => 'Rina Wijaya',
            'email' => 'rinawijaya@company.com',
            'message' => 'Selamat pagi, kami dari perwakilan HRD ingin menanyakan opsi promosi lowongan berbayar (Premium Plan) untuk menjangkau lebih banyak kandidat magang.',
            'is_read' => false,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\User;
use App\Models\School;
use App\Models\Company;
use App\Models\Major;
use App\Models\Student;
use App\Models\JobOpening;
use App\Models\Achievement;
use App\Models\Feedback;
use App\Models\InternshipApplication;
use App\Models\Province;
use App\Models\ActivityLog;
use App\Models\PreInternshipClass;

class AdminDashboardController extends Controller
{

    #[OA\Get(
        path: '/admin/dashboard',
        summary: 'Menampilkan data dashboard admin',
        description: 'Mengambil seluruh data ringkasan dashboard admin seperti summary, system metrics, insights, recommendations, recent activities, placement status, dan regional data.',
        tags: ['Admin Dashboard']
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil data dashboard'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    public function getDashboardData()
    {
        // ── Segmented school counts ───────────────────────────────────────
        $totalSekolah         = School::where('type', 'school')->count();
        $totalPerguruanTinggi = School::where('type', 'university')->count();

        // ── Segmented student counts (matching StudentController filters) ───────
        $totalSiswa = Student::where('is_verified', true)
            ->whereHas('school', function ($q) {
                $q->where('type', 'school');
            })
            ->whereIn('class', ['11', '12'])
            ->count();

        $totalMahasiswa = Student::where('is_verified', true)
            ->whereHas('school', function ($q) {
                $q->where('type', 'university');
            })
            ->count();

        // ── Application status counts (reused across sections) ────────────
        $totalApplications = InternshipApplication::count();
        $acceptedCount     = InternshipApplication::where('status', 'accepted')->count();
        $inProgressCount   = InternshipApplication::where('status', 'in_progress')->count();
        $rejectedCount     = InternshipApplication::where('status', 'rejected')->count();

        // ── Summary ───────────────────────────────────────────────────────
        $summary = [
            'total_users'            => User::count(),
            'total_schools'          => $totalSekolah,
            'total_perguruan_tinggi' => $totalPerguruanTinggi,
            'total_companies'        => Company::count(),
            'total_students'         => $totalSiswa,
            'total_mahasiswa'        => $totalMahasiswa,
            'total_all_students'     => $totalSiswa + $totalMahasiswa,
            'total_job_openings'     => JobOpening::count(),
            'total_achievements'     => Achievement::count(),
            'active_internships'     => \App\Models\Internship::where('is_completed', false)->count(), // BUG-05 fix: count actual active internships, not accepted applications
            'total_feedback'         => Feedback::count(),
        ];

        // ── System metrics ─────────────────────────────────────────────────
        $successRate = $this->calculateSuccessRate($totalApplications, $acceptedCount);
        $systemMetrics = [
            'new_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'active_users'      => User::where('last_login_at', '>=', now()->subDays(7))->count(),
            'total_placements'  => $acceptedCount,
            'success_rate'      => $successRate,
        ];

        // ── Regional data (companies + students per province) ─────────────
        $regionalData = Province::select('provinces.name as province')
            ->selectRaw('COUNT(DISTINCT companies.id) as company_count')
            ->selectRaw('COUNT(DISTINCT students.id) as student_count')
            ->leftJoin('city_regencies', 'city_regencies.province_id', '=', 'provinces.id')
            ->leftJoin('companies', 'companies.city_regency_id', '=', 'city_regencies.id')
            ->leftJoin('schools', 'schools.city_regency_id', '=', 'city_regencies.id')
            ->leftJoin('students', 'students.school_id', '=', 'schools.id')
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByRaw('company_count DESC')
            ->limit(5)
            ->get();

        // ── Placement status breakdown ─────────────────────────────────────
        $placementDivisor = max($totalApplications, 1);
        $placementStatus = [
            ['label' => 'Proses',   'value' => $inProgressCount, 'percent' => round($inProgressCount / $placementDivisor * 100), 'color' => '#3b82f6'],
            ['label' => 'Diterima', 'value' => $acceptedCount,   'percent' => round($acceptedCount   / $placementDivisor * 100), 'color' => '#22c55e'],
            ['label' => 'Ditolak',  'value' => $rejectedCount,   'percent' => round($rejectedCount   / $placementDivisor * 100), 'color' => '#ef4444'],
        ];

        // ── Pre-internship class summary ───────────────────────────────────
        $totalClasses   = PreInternshipClass::count();
        $ongoingClasses = PreInternshipClass::where('status', 'ongoing')->count();
        $lowProgressClasses = PreInternshipClass::where('status', 'ongoing')
            ->whereHas('enrollments', function ($q) {
                $q->whereRaw('attendance_count < total_sessions * 0.5 AND total_sessions > 0');
            })
            ->count();
        $preInternshipSummary = [
            'total'        => $totalClasses,
            'ongoing'      => $ongoingClasses,
            'needs_review' => $lowProgressClasses,
        ];

        // ── Deterministic insights (zero API calls) ────────────────────────
        $unplacedStudents = Student::where('status_magang', 'not_started')->count();
        $matchingJobCount = JobOpening::where('is_available', true)
            ->whereDate('closing_date', '>=', now()->toDateString())
            ->whereHas('internshipApplications')
            ->count();

        $insights = [
            [
                'key'      => 'unplaced_risk',
                'value'    => (string) $unplacedStudents,
                'unit'     => 'orang',
                'title'    => 'Siswa/Mahasiswa',
                'subtitle' => 'Belum Mendapat Penempatan',
                'badge'    => $unplacedStudents > 50 ? 'Tinggi' : ($unplacedStudents > 20 ? 'Sedang' : 'Rendah'),
                'color'    => $unplacedStudents > 50 ? 'red' : ($unplacedStudents > 20 ? 'orange' : 'green'), // BUG-06 fix: add green for Rendah
            ],
            [
                'key'      => 'matching_jobs',
                'value'    => (string) $matchingJobCount,
                'unit'     => 'lowongan',
                'title'    => 'Lowongan Aktif',
                'subtitle' => 'Dengan Pelamar Masuk',
                'badge'    => 'Aktif',
                'color'    => 'green',
            ],
            [
                'key'      => 'success_rate',
                'value'    => $successRate . '%',
                'unit'     => '',
                'title'    => 'Estimasi Keberhasilan',
                'subtitle' => 'Penempatan Terselesaikan',
                'badge'    => $successRate >= 70 ? 'Optimis' : ($successRate >= 40 ? 'Moderat' : 'Perlu Perhatian'),
                'color'    => $successRate >= 70 ? 'blue' : ($successRate >= 40 ? 'orange' : 'red'),
            ],
        ];

        // ── Deterministic recommendations (from real DB conditions) ────────
        $recommendations = [];

        $studentsWithoutMentor = Student::whereIn('status_magang', ['not_started', 'ongoing'])
            ->whereDoesntHave('user', function ($q) {
                $q->whereHas('mentorAssignments');
            })->count();
        if ($studentsWithoutMentor > 0) {
            $recommendations[] = [
                'key'           => 'no_mentor',
                'icon'          => 'UserX',
                'color'         => 'red',
                'title'         => "Prioritaskan peserta tanpa pembimbing",
                'desc'          => "{$studentsWithoutMentor} peserta belum memiliki pembimbing. Tindakan cepat disarankan.",
                'priority'      => 'Prioritas Tinggi',
                'priorityColor' => 'red',
            ];
        }

        $zeroApplicantJobs = JobOpening::where('is_available', true)
            ->whereDate('closing_date', '>=', now()->toDateString())
            ->whereDoesntHave('internshipApplications')
            ->count();
        if ($zeroApplicantJobs > 0) {
            $recommendations[] = [
                'key'           => 'zero_applicants',
                'icon'          => 'Building2',
                'color'         => 'orange',
                'title'         => "Hubungi {$zeroApplicantJobs} industri potensial",
                'desc'          => "Lowongan aktif tanpa pelamar ditemukan. Rekomendasikan ke siswa yang belum melamar.",
                'priority'      => 'Prioritas Sedang',
                'priorityColor' => 'orange',
            ];
        }

        if ($lowProgressClasses > 0) {
            $recommendations[] = [
                'key'           => 'low_progress_classes',
                'icon'          => 'UserCircle',
                'color'         => 'blue',
                'title'         => "Evaluasi {$lowProgressClasses} kelas pra-magang",
                'desc'          => "Kelas dengan kehadiran rendah di bawah 50%. Perlu tindak lanjut segera.",
                'priority'      => 'Prioritas Sedang',
                'priorityColor' => 'orange',
            ];
        }

        $unverifiedSchools = School::where('is_verified', false)->count();
        if ($unverifiedSchools > 0) {
            $recommendations[] = [
                'key'           => 'unverified_schools',
                'icon'          => 'Building2',
                'color'         => 'green',
                'title'         => "Verifikasi {$unverifiedSchools} sekolah/perguruan tinggi",
                'desc'          => "Data belum terverifikasi. Pastikan data terbaru agar rekomendasi lebih akurat.",
                'priority'      => 'Prioritas Rendah',
                'priorityColor' => 'green',
            ];
        }

        // ── Recent activities from activity_logs ───────────────────────────
        $recentActivities = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($log) => [
                'action'        => $log->action,
                'resource_type' => $log->resource_type,
                'resource_name' => $log->resource_name,
                'description'   => $log->description,
                'user_name'     => $log->user?->name ?? 'Sistem',
                'time_ago'      => $log->created_at->diffForHumans(),
            ]);

        // ── Recent registered users ─────────────────────────────────────────
        $recentRegistrations = User::with(['student.school', 'school', 'company'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($u) {
                $name = $u->student?->name ?? $u->school?->name ?? $u->company?->name ?? $u->username;
                $institution = $u->student?->school?->name ?? null;
                $roleLabel = match ($u->role) {
                    'student' => $u->student?->school?->type === 'university' ? 'Mahasiswa' : 'Siswa',
                    'school' => $u->school?->type === 'university' ? 'Perguruan Tinggi' : 'Sekolah',
                    'company' => 'Industri',
                    'super_admin' => 'Admin',
                    default => ucfirst($u->role),
                };
                $roleColor = match ($roleLabel) {
                    'Siswa' => 'blue',
                    'Mahasiswa' => 'orange',
                    'Industri' => 'green',
                    'Sekolah' => 'purple',
                    'Perguruan Tinggi' => 'yellow',
                    default => 'blue',
                };
                return [
                    'id'            => $u->id,
                    'name'          => $name,
                    'username'      => $u->username,
                    'email'         => $u->email,
                    'role'          => $u->role,
                    'role_label'    => $roleLabel,
                    'role_color'    => $roleColor,
                    'institution'   => $institution,
                    'photo_profile' => $u->photo_profile,
                    'created_at'    => $u->created_at?->toISOString(),
                    'time_ago'      => $u->created_at?->diffForHumans() ?? 'baru saja',
                ];
            });

        // ── AI Matching Score (dynamic based on real data) ───────────────
        $activeJobOpenings = JobOpening::where('is_available', true)->with('field')->get();
        $totalActiveJobs = $activeJobOpenings->count();

        // Mapping major names to suitable field names
        $majorFieldMapping = [
            'Rekayasa Perangkat Lunak' => ['Web Development', 'Mobile App Development', 'UI/UX Design'],
            'Teknik Komputer dan Jaringan' => ['Network & Security'],
            'Multimedia' => ['UI/UX Design', 'Digital Marketing'],
            'Teknik Informatika' => ['Web Development', 'Mobile App Development', 'Data Analysis'],
            'Sistem Informasi' => ['Data Analysis', 'Web Development'],
            'Desain Komunikasi Visual' => ['UI/UX Design', 'Digital Marketing'],
        ];

        // Retrieve all majors that are accepted
        $allMajors = Major::where('is_accepted', true)->withCount('students')->get();

        $matchingScores = [
            'smk' => [],
            'mahasiswa' => [],
        ];

        // Color and icon mappings for frontend UI
        $uiPropsKnown = [
            'Rekayasa Perangkat Lunak' => ['icon' => 'Code2', 'color' => 'green', 'label' => 'RPL'],
            'Teknik Komputer dan Jaringan' => ['icon' => 'Network', 'color' => 'purple', 'label' => 'TKJ'],
            'Multimedia' => ['icon' => 'ImageIcon', 'color' => 'orange', 'label' => 'Multimedia'],
            'Teknik Informatika' => ['icon' => 'Cpu', 'color' => 'blue', 'label' => 'Informatika'],
            'Sistem Informasi' => ['icon' => 'LineChart', 'color' => 'green', 'label' => 'Sistem Informasi'],
            'Desain Komunikasi Visual' => ['icon' => 'Zap', 'color' => 'orange', 'label' => 'DKV'],
        ];

        // Pre-load all placed student counts in a single query
        $placedByMajor = Student::selectRaw('major_id, COUNT(*) as placed')
            ->whereIn('status_magang', ['ongoing', 'completed'])
            ->whereNotNull('major_id')
            ->groupBy('major_id')
            ->pluck('placed', 'major_id');

        // Pre-load application counts and accepted counts per major
        $applicationStatsByMajor = InternshipApplication::join('curriculum_vitaes', 'internship_applications.curriculum_vitae_id', '=', 'curriculum_vitaes.id')
            ->join('students', 'curriculum_vitaes.student_id', '=', 'students.id')
            ->selectRaw('students.major_id, COUNT(internship_applications.id) as total_apps, SUM(CASE WHEN internship_applications.status = "accepted" THEN 1 ELSE 0 END) as accepted_apps')
            ->whereNotNull('students.major_id')
            ->groupBy('students.major_id')
            ->get()
            ->keyBy('major_id');

        // Pre-load student counts by school type per major for fallback level detection
        $schoolTypeByMajor = Student::join('schools', 'students.school_id', '=', 'schools.id')
            ->selectRaw('students.major_id, schools.type, COUNT(*) as cnt')
            ->whereNotNull('students.major_id')
            ->groupBy('students.major_id', 'schools.type')
            ->get()
            ->groupBy('major_id');

        foreach ($allMajors as $major) {
            $suitableFields = $majorFieldMapping[$major->name] ?? [];
            
            // Calculate demand: active jobs matching suitable fields or major name keywords
            $demandJobsCount = $activeJobOpenings->filter(function ($job) use ($suitableFields, $major) {
                if (!empty($suitableFields) && in_array($job->field?->name, $suitableFields)) {
                    return true;
                }
                $searchable = strtolower(($job->title ?? '') . ' ' . ($job->field?->name ?? ''));
                $majorLower = strtolower($major->name);
                return str_contains($searchable, $majorLower) || (!empty($job->field?->name) && str_contains($majorLower, strtolower($job->field->name)));
            })->count();

            $demandRate = $totalActiveJobs > 0 ? ($demandJobsCount / $totalActiveJobs) * 100 : 0;

            // Calculate REAL metrics strictly from database records
            $totalStudents = $major->students_count;
            $placedStudents = $placedByMajor[$major->id] ?? 0;
            $appStats = $applicationStatsByMajor[$major->id] ?? null;
            $totalApps = $appStats ? (int) $appStats->total_apps : 0;
            $acceptedApps = $appStats ? (int) $appStats->accepted_apps : 0;

            if ($totalStudents === 0 && $totalApps === 0) {
                // Strictly 0% if no student has selected/registered or applied in this major
                $scoreValue = 0;
            } else {
                // REAL placement rate (placed / total students in major)
                $placementRate = $totalStudents > 0 ? ($placedStudents / $totalStudents) * 100 : 0;

                // REAL acceptance rate (accepted applications / total applications in major)
                $acceptanceRate = $totalApps > 0 ? ($acceptedApps / $totalApps) * 100 : 0;

                // REAL demand rate (matching active jobs / total active jobs)
                $demandRate = $totalActiveJobs > 0 ? ($demandJobsCount / $totalActiveJobs) * 100 : 0;

                // Weighted score strictly from real database activity
                $scoreValue = round(($placementRate * 0.50) + ($acceptanceRate * 0.30) + ($demandRate * 0.20));
                $scoreValue = max(0, min(100, (int) $scoreValue));
            }

            // Dynamic props (label, icon, color)
            if (isset($uiPropsKnown[$major->name])) {
                $props = $uiPropsKnown[$major->name];
            } else {
                $words = array_values(array_filter(explode(' ', trim($major->name))));
                if (count($words) === 1) {
                    $label = $words[0];
                } else {
                    $stopWords = ['dan', 'atau', 'ke', 'di', 'untuk', 'teknik', 'ilmu'];
                    $initials = '';
                    foreach ($words as $w) {
                        if (!in_array(strtolower($w), $stopWords) && strlen($w) > 0) {
                            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
                        }
                    }
                    $label = strlen($initials) >= 2 ? $initials : $words[0];
                }

                $lower = strtolower($major->name);
                if (str_contains($lower, 'perangkat') || str_contains($lower, 'software') || str_contains($lower, 'web')) {
                    $props = ['icon' => 'Code2', 'color' => 'green', 'label' => $label];
                } elseif (str_contains($lower, 'jaringan') || str_contains($lower, 'network')) {
                    $props = ['icon' => 'Network', 'color' => 'purple', 'label' => $label];
                } elseif (str_contains($lower, 'desain') || str_contains($lower, 'multimedia') || str_contains($lower, 'visual')) {
                    $props = ['icon' => 'Zap', 'color' => 'orange', 'label' => $label];
                } elseif (str_contains($lower, 'informatika') || str_contains($lower, 'komputer')) {
                    $props = ['icon' => 'Cpu', 'color' => 'blue', 'label' => $label];
                } else {
                    $props = ['icon' => 'Monitor', 'color' => 'blue', 'label' => $label];
                }
            }

            $item = [
                'label' => $props['label'],
                'full_name' => $major->name,
                'value' => (int) $scoreValue,
                'color' => $props['color'],
                'icon' => $props['icon'],
            ];

            // Determine level classification (smk vs mahasiswa)
            if ($major->level === 'smk') {
                $isSmk = true;
            } elseif ($major->level === 'college') {
                $isSmk = false;
            } else {
                // Fallback: check linked school types
                $schoolTypes = $schoolTypeByMajor->get($major->id);
                $univCount = $schoolTypes ? $schoolTypes->where('type', 'university')->sum('cnt') : 0;
                $schoolCount = $schoolTypes ? $schoolTypes->where('type', 'school')->sum('cnt') : 0;
                $isSmk = $schoolCount >= $univCount;
            }

            if ($isSmk) {
                $matchingScores['smk'][] = $item;
            } else {
                $matchingScores['mahasiswa'][] = $item;
            }
        }

        // Sort both arrays by value descending
        usort($matchingScores['smk'], function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        usort($matchingScores['mahasiswa'], function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        return response()->json([
            'summary'                => $summary,
            'system_metrics'         => $systemMetrics,
            'regional_data'          => $regionalData,
            'placement_status'       => $placementStatus,
            'insights'               => $insights,
            'recommendations'        => $recommendations,
            'recent_activities'      => $recentActivities,
            'recent_registrations'   => $recentRegistrations,
            'pre_internship_summary' => $preInternshipSummary,
            'matching_scores'        => $matchingScores,
        ]);
    }

    /**
     * Calculate placement success rate from real InternshipApplication statuses.
     * Success = accepted / total applications × 100
     */
    private function calculateSuccessRate(int $total, int $accepted): float
    {
        if ($total === 0) {
            return 0.0;
        }
        return round(($accepted / $total) * 100, 1);
    }
}
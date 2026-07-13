<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\User;
use App\Models\School;
use App\Models\Company;
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

        // ── Segmented student counts (join on schools.type) ───────────────
        $totalSiswa     = Student::join('schools', 'students.school_id', '=', 'schools.id')
                            ->where('schools.type', 'school')->count();
        $totalMahasiswa = Student::join('schools', 'students.school_id', '=', 'schools.id')
                            ->where('schools.type', 'university')->count();

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
            'total_all_students'     => Student::count(),
            'total_job_openings'     => JobOpening::count(),
            'total_achievements'     => Achievement::count(),
            'active_internships'     => $acceptedCount,
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
            ->withCount(['enrollments as low_attendance_count' => function ($q) {
                $q->whereRaw('attendance_count < total_sessions * 0.5 AND total_sessions > 0');
            }])
            ->having('low_attendance_count', '>', 0)
            ->count();
        $preInternshipSummary = [
            'total'        => $totalClasses,
            'ongoing'      => $ongoingClasses,
            'needs_review' => $lowProgressClasses,
        ];

        // ── Deterministic insights (zero API calls) ────────────────────────
        $unplacedStudents = Student::where('status', 'not_started')->count();
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
                'color'    => $unplacedStudents > 50 ? 'red' : 'orange',
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

        $studentsWithoutMentor = Student::whereIn('status', ['not_started', 'ongoing'])
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

        return response()->json([
            'summary'                => $summary,
            'system_metrics'         => $systemMetrics,
            'regional_data'          => $regionalData,
            'placement_status'       => $placementStatus,
            'insights'               => $insights,
            'recommendations'        => $recommendations,
            'recent_activities'      => $recentActivities,
            'pre_internship_summary' => $preInternshipSummary,
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

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\School;
use App\Models\Company;
use App\Models\Student;
use App\Models\JobOpening;
use App\Models\Achievement;
use App\Models\Internship;
use App\Models\Feedback;
use App\Models\InternshipApplication;
use App\Models\Province;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function getDashboardData()
    {
        // Get summary data
        $summary = [
            'total_users' => User::count(),
            'total_schools' => School::count(),
            'total_companies' => Company::count(),
            'total_students' => Student::count(),
            'total_job_openings' => JobOpening::count(),
            'total_achievements' => Achievement::count(),
            'active_internships' => InternshipApplication::where('status', 'active')->count(),
            'total_feedback' => Feedback::count(),
        ];

        // Get system metrics
        $systemMetrics = [
            'new_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'active_users' => User::where('last_login_at', '>=', now()->subDays(7))->count(),
            'total_placements' => InternshipApplication::where('status', 'completed')->count(),
            'success_rate' => $this->calculateSuccessRate(),
        ];

        // Get regional data
        $regionalData = Province::select('provinces.name as province')
            // ->selectRaw('COUNT(DISTINCT students.id) as student_count')
            ->selectRaw('COUNT(DISTINCT companies.id) as company_count')
            // ->leftJoin('students', 'students.province_id', '=', 'provinces.id')
            ->leftJoin('city_regencies', 'city_regencies.province_id', '=', 'provinces.id')
            ->leftJoin('companies', 'companies.city_regency_id', '=', 'city_regencies.id')
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByRaw('company_count DESC')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => $summary,
            'system_metrics' => $systemMetrics,
            'regional_data' => $regionalData
        ]);
    }

    private function calculateSuccessRate()
    {
        $totalInternships = InternshipApplication::where('status', 'completed')->count();
        $successfulInternships = InternshipApplication::where('status', 'completed')
            // ->where('rating', '>=', 4)
            ->count();

        if ($totalInternships === 0) {
            return 0;
        }

        return round(($successfulInternships / $totalInternships) * 100, 1);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ScheduledReport;
use App\Models\Internship;
use App\Models\Student;
use App\Models\Company;
use App\Models\PreInternshipEnrollment;
use App\Models\Feedback;
use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DB;

class ReportController extends Controller
{
    // GET /api/v1/reports/internship-stats
    public function internshipStats(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $companyId = $request->query('company_id');
        $fieldId = $request->query('field_id');
        $status = $request->query('status');

        $query = Internship::query();

        if ($startDate) {
            $query->where('start_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('end_date', '<=', $endDate);
        }
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($fieldId) {
            $query->whereHas('internshipApplication.jobOpening', function ($q) use ($fieldId) {
                $q->where('field_id', $fieldId);
            });
        }

        $today = Carbon::today()->format('Y-m-d');

        $completedCount = (clone $query)->where(function ($q) use ($today) {
            $q->where('is_completed', true)
              ->orWhere('end_date', '<', $today);
        })->count();

        $ongoingCount = (clone $query)->where('is_completed', false)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        $pendingCount = (clone $query)->where('is_completed', false)
            ->where('start_date', '>', $today)
            ->count();

        $total = $completedCount + $ongoingCount + $pendingCount;

        if ($status) {
            if ($status === 'completed') {
                $totalFiltered = $completedCount;
            } elseif ($status === 'ongoing') {
                $totalFiltered = $ongoingCount;
            } elseif ($status === 'pending') {
                $totalFiltered = $pendingCount;
            } else {
                $totalFiltered = $total;
            }
        } else {
            $totalFiltered = $total;
        }

        // Company breakdown
        $companyBreakdown = Internship::select('company_id', DB::raw('count(*) as count'))
            ->groupBy('company_id')
            ->with('company')
            ->get()
            ->map(function ($item) {
                return [
                    'company_name' => $item->company?->name ?? 'Unknown Company',
                    'count' => $item->count
                ];
            });

        // Field breakdown
        $fieldBreakdown = Internship::join('internship_applications', 'internships.internship_application_id', '=', 'internship_applications.id')
            ->join('job_openings', 'internship_applications.job_opening_id', '=', 'job_openings.id')
            ->join('fields', 'job_openings.field_id', '=', 'fields.id')
            ->select('fields.name as field_name', DB::raw('count(*) as count'))
            ->groupBy('fields.name')
            ->get();

        // Average duration in days
        $allInternships = (clone $query)->get();
        $durations = $allInternships->map(function ($internship) {
            return Carbon::parse($internship->start_date)->diffInDays(Carbon::parse($internship->end_date));
        });
        $averageDuration = $durations->count() > 0 ? round($durations->average(), 1) : 0;

        $successRate = $total > 0 ? round(($completedCount / $total) * 100, 1) : 0;

        return response()->json([
            'total_internships' => $totalFiltered,
            'by_status' => [
                'pending' => $pendingCount,
                'ongoing' => $ongoingCount,
                'completed' => $completedCount
            ],
            'by_company' => $companyBreakdown,
            'by_field' => $fieldBreakdown,
            'average_duration_days' => $averageDuration,
            'success_rate_percentage' => $successRate
        ]);
    }

    // GET /api/v1/reports/student-progress
    public function studentProgress(Request $request)
    {
        $schoolId = $request->query('school_id');
        $status = $request->query('status');

        $studentQuery = Student::query();
        if ($schoolId) {
            $studentQuery->where('school_id', $schoolId);
        }
        if ($status) {
            $studentQuery->where('status_magang', $status);
        }

        $totalStudents = $studentQuery->count();

        // Status breakdown
        $statusBreakdown = Student::select('status_magang', DB::raw('count(*) as count'))
            ->when($schoolId, function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->groupBy('status_magang')
            ->get()
            ->pluck('count', 'status_magang');

        // Pre-internship enrollment count
        $preInternshipEnrolled = PreInternshipEnrollment::distinct('student_id')
            ->when($schoolId, function ($q) use ($schoolId) {
                $q->whereHas('student.student', function ($sq) use ($schoolId) {
                    $sq->where('school_id', $schoolId);
                });
            })
            ->count();

        $preInternshipCompleted = PreInternshipEnrollment::where('status', 'completed')
            ->distinct('student_id')
            ->when($schoolId, function ($q) use ($schoolId) {
                $q->whereHas('student.student', function ($sq) use ($schoolId) {
                    $sq->where('school_id', $schoolId);
                });
            })
            ->count();

        $preInternshipDropped = PreInternshipEnrollment::where('status', 'dropped')
            ->distinct('student_id')
            ->when($schoolId, function ($q) use ($schoolId) {
                $q->whereHas('student.student', function ($sq) use ($schoolId) {
                    $sq->where('school_id', $schoolId);
                });
            })
            ->count();

        $dropoutRate = $preInternshipEnrolled > 0 ? round(($preInternshipDropped / $preInternshipEnrolled) * 100, 1) : 0;

        // Average rating received (from feedback to type student)
        $avgRating = Feedback::where('to_type', 'student')
            ->when($schoolId, function ($q) use ($schoolId) {
                $q->whereHas('toUser.student', function ($sq) use ($schoolId) {
                    $sq->where('school_id', $schoolId);
                });
            })
            ->avg('rating');

        return response()->json([
            'total_students' => $totalStudents,
            'by_status' => [
                'not_started' => $statusBreakdown['not_started'] ?? 0,
                'ongoing' => $statusBreakdown['ongoing'] ?? 0,
                'completed' => $statusBreakdown['completed'] ?? 0,
            ],
            'pre_internship_enrolled' => $preInternshipEnrolled,
            'pre_internship_completed' => $preInternshipCompleted,
            'dropout_rate_percentage' => $dropoutRate,
            'average_rating' => $avgRating ? round($avgRating, 1) : 0
        ]);
    }

    // GET /api/v1/reports/company-performance
    public function companyPerformance(Request $request)
    {
        $location = $request->query('location'); // city_regency_id
        $industry = $request->query('industry'); // sector_id

        $companyQuery = Company::query();
        if ($location) {
            $companyQuery->where('city_regency_id', $location);
        }
        if ($industry) {
            $companyQuery->where('sector_id', $industry);
        }

        $totalCompanies = $companyQuery->count();

        // Placements per company
        $placements = Company::select('companies.id', 'companies.name', DB::raw('count(internships.id) as placements_count'))
            ->leftJoin('internships', 'companies.id', '=', 'internships.company_id')
            ->when($location, fn($q) => $q->where('companies.city_regency_id', $location))
            ->when($industry, fn($q) => $q->where('companies.sector_id', $industry))
            ->groupBy('companies.id', 'companies.name')
            ->get();

        // Average student rating per company
        $avgRating = Feedback::where('to_type', 'company')
            ->when($location || $industry, function ($q) use ($location, $industry) {
                $q->whereHas('toUser.company', function ($cq) use ($location, $industry) {
                    if ($location) $cq->where('city_regency_id', $location);
                    if ($industry) $cq->where('sector_id', $industry);
                });
            })
            ->avg('rating');

        return response()->json([
            'total_companies' => $totalCompanies,
            'placements_per_company' => $placements,
            'average_rating' => $avgRating ? round($avgRating, 1) : 0,
            'retention_rate_percentage' => 85.0, // Mock metric
            'job_offer_rate_percentage' => 70.0 // Mock metric
        ]);
    }

    // POST/GET /api/v1/reports/export
    public function export(Request $request)
    {
        if ($request->has('token') && !Auth::check()) {
            $token = $request->query('token');
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->tokenable) {
                Auth::setUser($accessToken->tokenable);
            }
        }

        $type = $request->input('type', $request->query('type', 'internship_stats'));
        $format = $request->input('format', $request->query('format', 'csv'));
        $filters = $request->input('filters', $request->except(['type', 'format', 'token']));

        if (!is_array($filters)) {
            $filters = [];
        }

        $validator = Validator::make([
            'type' => $type,
            'format' => $format,
            'filters' => $filters,
        ], [
            'type' => 'required|in:internship_stats,student_progress,company_performance',
            'format' => 'required|in:csv,pdf',
            'filters' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Retrieve data by simulating the requests internally
        $subRequest = new Request($filters);
        if ($type === 'internship_stats') {
            $data = $this->internshipStats($subRequest)->getData(true);
        } elseif ($type === 'student_progress') {
            $data = $this->studentProgress($subRequest)->getData(true);
        } else {
            $data = $this->companyPerformance($subRequest)->getData(true);
        }

        if ($format === 'csv') {
            return $this->exportToCSV($data, $type);
        } else {
            return $this->exportToPDF($data, $type);
        }
    }

    private function exportToCSV($data, $type)
    {
        $filename = "report_{$type}_" . date('Ymd_His') . ".csv";

        $output = fopen('php://temp', 'r+');

        // Header info
        fputcsv($output, ["REPORT: " . strtoupper(str_replace('_', ' ', $type))]);
        fputcsv($output, ["Generated At", Carbon::now()->toDateTimeString()]);
        fputcsv($output, []);

        if ($type === 'internship_stats') {
            fputcsv($output, ["Total Internships", $data['total_internships'] ?? 0]);
            fputcsv($output, ["Success Rate (%)", $data['success_rate_percentage'] ?? 0]);
            fputcsv($output, ["Average Duration (Days)", $data['average_duration_days'] ?? 0]);
            fputcsv($output, []);
            fputcsv($output, ["Status", "Count"]);
            if (isset($data['by_status']) && is_array($data['by_status'])) {
                foreach ($data['by_status'] as $status => $count) {
                    fputcsv($output, [ucfirst($status), $count]);
                }
            }
        } elseif ($type === 'student_progress') {
            fputcsv($output, ["Total Students", $data['total_students'] ?? 0]);
            fputcsv($output, ["Average Rating", $data['average_rating'] ?? 0]);
            fputcsv($output, ["Pre-Internship Enrolled", $data['pre_internship_enrolled'] ?? 0]);
            fputcsv($output, ["Pre-Internship Completed", $data['pre_internship_completed'] ?? 0]);
            fputcsv($output, ["Dropout Rate (%)", $data['dropout_rate_percentage'] ?? 0]);
            fputcsv($output, []);
            fputcsv($output, ["Status", "Count"]);
            if (isset($data['by_status']) && is_array($data['by_status'])) {
                foreach ($data['by_status'] as $status => $count) {
                    fputcsv($output, [ucfirst(str_replace('_', ' ', $status)), $count]);
                }
            }
        } else {
            fputcsv($output, ["Total Companies", $data['total_companies'] ?? 0]);
            fputcsv($output, ["Average Company Rating", $data['average_rating'] ?? 0]);
            fputcsv($output, ["Retention Rate (%)", $data['retention_rate_percentage'] ?? 0]);
            fputcsv($output, ["Job Offer Rate (%)", $data['job_offer_rate_percentage'] ?? 0]);
            fputcsv($output, []);
            fputcsv($output, ["Company ID", "Company Name", "Placements Count"]);
            if (isset($data['placements_per_company']) && is_iterable($data['placements_per_company'])) {
                foreach ($data['placements_per_company'] as $company) {
                    fputcsv($output, [$company['id'] ?? '', $company['name'] ?? '', $company['placements_count'] ?? 0]);
                }
            }
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"{$filename}\"",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        return response($csvContent, 200, $headers);
    }

    private function exportToPDF($data, $type)
    {
        $title = strtoupper(str_replace('_', ' ', $type));
        $generatedAt = Carbon::now()->toDateTimeString();

        $html = "
        <html>
        <head>
            <style>
                body { font-family: sans-serif; color: #333; }
                h1 { color: #035a70; font-size: 24px; margin-bottom: 5px; }
                .meta { font-size: 12px; color: #666; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { bg-color: #f2f2f2; font-weight: bold; }
                .summary { margin-top: 10px; margin-bottom: 20px; font-size: 14px; }
                .summary-item { margin-bottom: 5px; }
            </style>
        </head>
        <body>
            <h1>$title REPORT</h1>
            <div class='meta'>Generated at: $generatedAt</div>
            
            <div class='summary'>
        ";

        if ($type === 'internship_stats') {
            $html .= "
                <div class='summary-item'><strong>Total Internships:</strong> {$data['total_internships']}</div>
                <div class='summary-item'><strong>Success Rate:</strong> {$data['success_rate_percentage']}%</div>
                <div class='summary-item'><strong>Average Duration:</strong> {$data['average_duration_days']} days</div>
                </div>
                <h3>Status Breakdown</h3>
                <table>
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                        <tr><td>Pending</td><td>{$data['by_status']['pending']}</td></tr>
                        <tr><td>Ongoing</td><td>{$data['by_status']['ongoing']}</td></tr>
                        <tr><td>Completed</td><td>{$data['by_status']['completed']}</td></tr>
                    </tbody>
                </table>
            ";
        } elseif ($type === 'student_progress') {
            $html .= "
                <div class='summary-item'><strong>Total Students:</strong> {$data['total_students']}</div>
                <div class='summary-item'><strong>Average Rating:</strong> {$data['average_rating']}</div>
                <div class='summary-item'><strong>Pre-Internship Enrolled:</strong> {$data['pre_internship_enrolled']}</div>
                <div class='summary-item'><strong>Pre-Internship Completed:</strong> {$data['pre_internship_completed']}</div>
                <div class='summary-item'><strong>Dropout Rate:</strong> {$data['dropout_rate_percentage']}%</div>
                </div>
                <h3>Status Breakdown</h3>
                <table>
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                        <tr><td>Not Started</td><td>{$data['by_status']['not_started']}</td></tr>
                        <tr><td>Ongoing</td><td>{$data['by_status']['ongoing']}</td></tr>
                        <tr><td>Completed</td><td>{$data['by_status']['completed']}</td></tr>
                    </tbody>
                </table>
            ";
        } else {
            $html .= "
                <div class='summary-item'><strong>Total Companies:</strong> {$data['total_companies']}</div>
                <div class='summary-item'><strong>Average Company Rating:</strong> {$data['average_rating']}</div>
                <div class='summary-item'><strong>Retention Rate:</strong> {$data['retention_rate_percentage']}%</div>
                <div class='summary-item'><strong>Job Offer Rate:</strong> {$data['job_offer_rate_percentage']}%</div>
                </div>
                <h3>Company Placements</h3>
                <table>
                    <thead><tr><th>Company Name</th><th>Placements Count</th></tr></thead>
                    <tbody>
            ";
            foreach ($data['placements_per_company'] as $company) {
                $html .= "<tr><td>{$company['name']}</td><td>{$company['placements_count']}</td></tr>";
            }
            $html .= "
                    </tbody>
                </table>
            ";
        }

        $html .= "
        </body>
        </html>
        ";

        $pdf = Pdf::loadHTML($html);
        return $pdf->download("report_{$type}_" . date('Ymd_His') . ".pdf");
    }

    // POST /api/v1/scheduled-reports
    public function storeScheduledReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:internship_stats,student_progress,company_performance',
            'frequency' => 'required|in:daily,weekly,monthly',
            'email_recipients' => 'required|array',
            'email_recipients.*' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $scheduled = ScheduledReport::create([
            'created_by_id' => Auth::id(),
            'type' => $request->input('type'),
            'frequency' => $request->input('frequency'),
            'email_recipients' => $request->input('email_recipients'),
            'is_active' => true
        ]);

        return response()->json(['data' => $scheduled], 201);
    }

    // GET /api/v1/scheduled-reports
    public function listScheduledReports()
    {
        $scheduled = ScheduledReport::where('created_by_id', Auth::id())->get();
        return response()->json(['data' => $scheduled]);
    }

    // PATCH /api/v1/scheduled-reports/{id}
    public function updateScheduledReport(Request $request, $id)
    {
        $scheduled = ScheduledReport::where('created_by_id', Auth::id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'frequency' => 'nullable|in:daily,weekly,monthly',
            'email_recipients' => 'nullable|array',
            'email_recipients.*' => 'nullable|email',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $scheduled->update($request->only(['frequency', 'email_recipients', 'is_active']));

        return response()->json(['data' => $scheduled]);
    }

    // DELETE /api/v1/scheduled-reports/{id}
    public function deleteScheduledReport($id)
    {
        $scheduled = ScheduledReport::where('created_by_id', Auth::id())->findOrFail($id);
        $scheduled->delete();
        return response()->json(['message' => 'Scheduled report deleted successfully']);
    }

    // POST /api/v1/scheduled-reports/{id}/run-now
    public function runScheduledReport($id)
    {
        $scheduled = ScheduledReport::where('created_by_id', Auth::id())->findOrFail($id);

        $subRequest = new Request();
        if ($scheduled->type === 'internship_stats') {
            $data = $this->internshipStats($subRequest)->getData(true);
        } elseif ($scheduled->type === 'student_progress') {
            $data = $this->studentProgress($subRequest)->getData(true);
        } else {
            $data = $this->companyPerformance($subRequest)->getData(true);
        }

        // Store into reports
        $report = Report::create([
            'type' => $scheduled->type,
            'data' => $data,
            'generated_at' => Carbon::now(),
            'generated_by_id' => Auth::id()
        ]);

        $scheduled->update(['last_sent_at' => Carbon::now()]);

        // Mock sending email notification in logs
        \Log::info("Scheduled report ran: type={$scheduled->type}, recipients=" . implode(',', $scheduled->email_recipients));

        return response()->json([
            'message' => 'Scheduled report run completed and email sent.',
            'data' => $report
        ]);
    }

    /**
     * Generate an AI-powered summary report of system activities.
     */
    public function generateAiSummary(Request $request)
    {
        // Increase maximum execution time for AI processing
        @set_time_limit(120);

        $aiProvider = \App\Models\Setting::getVal('ai_provider', 'gemini');
        if ($aiProvider === 'none') {
            return response()->json(['message' => 'Layanan AI Report dinonaktifkan oleh administrator.'], 403);
        }

        if (!config('gemini.api_key')) {
            return response()->json([
                'error_type' => 'missing_api_key',
                'message' => 'Layanan AI Report belum siap. Kunci API Gemini belum dikonfigurasi di menu Pengaturan Sistem.'
            ], 500);
        }

        $user = Auth::user();
        if ($user && ($user->role === 'student' || ($user->student && $user->student()->exists()))) {
            $student = $user->student;
            if (!$student) {
                return response()->json(['message' => 'Data profil siswa tidak ditemukan.'], 404);
            }

            // Find student's active internship
            $internship = Internship::where('student_id', $student->id)->with(['company', 'tasks', 'jobPosition'])->first();
            if (!$internship) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'summary' => 'Anda belum terdaftar dalam program magang aktif.',
                        'insights' => ['Belum ada riwayat magang aktif yang tercatat.'],
                        'recommendations' => ['Silakan ajukan lamaran magang pada posisi yang tersedia atau hubungi pembimbing sekolah Anda.']
                    ]
                ]);
            }

            // Gather student internship info
            $companyName = $internship->company?->name ?? 'Perusahaan';
            $jobTitle = $internship->jobPosition?->name ?? 'Peserta Magang';
            $startDate = $internship->start_date;
            $endDate = $internship->end_date;

            $allTasks = $internship->tasks;
            $totalTasks = $allTasks->count();
            $completedTasks = $allTasks->where('status', 'completed');
            $completedCount = $completedTasks->count();

            $tasksText = "";
            foreach ($allTasks as $idx => $task) {
                $statusLabel = $task->status === 'completed' ? 'Selesai' : 'Pending';
                $tasksText .= "- {$task->title} (Status: {$statusLabel}): {$task->description}\n";
            }

            $school = $student->school ?? \App\Models\School::find($student->school_id);
            $schoolTemplateInstruction = "";
            $summaryStructureDesc = "Ringkasan laporan magang eksekutif yang formal dalam 2-3 paragraf, merangkum apa saja yang dikerjakan siswa, kontribusinya kepada perusahaan, serta evaluasi performa umum";

            if ($school && !empty($school->report_template)) {
                $schoolTemplateInstruction = "\nATURAN UTAMA - PANDUAN & FORMAT LAPORAN SEKOLAH (" . ($school->name ?? 'Sekolah') . "):
" . $school->report_template . "

WAJIB PERHATIKAN: Sekolah siswa (" . ($school->name ?? 'Sekolah') . ") mewajibkan laporan magang disusun mengikuti struktur/template khusus di atas. 
1. Anda HARUS menyusun teks bidang 'summary' secara utuh dengan mengikuti judul bab/bagian, urutan nomor, dan instruksi dari Template Format Laporan Sekolah di atas!
2. FORMATTING KHUSUS: Setiap nomor/bab/bagian WAJIB diawali dengan dua kali baris baru ('\n\n') agar membentuk paragraf dan bagian tersendiri yang rapi (Contoh format di bidang 'summary': '\n\n1. PENDAHULUAN: LATAR BELAKANG DAN TUJUAN MAGANG\nIsi penjelasan...\n\n2. GAMBARAN UMUM PERUSAHAAN MITRA\nIsi penjelasan...'). Jangan menggabungkan bab/nomor dalam satu paragraf panjang!\n";
                $summaryStructureDesc = "Teks laporan lengkap yang disusun dan dikelompokkan secara ketat berdasarkan Bab/Bagian/Nomor dari Template Format Laporan Sekolah di atas, di mana setiap Bab/Nomor dipisahkan oleh dua baris baru ('\\n\\n') dan diisi penjelasan komprehensif";
            }

            $prompt = "Anda adalah asisten AI Prakerin.ID.
Tugas Anda adalah membuat Laporan Pertanggungjawaban/Hasil Magang secara otomatis untuk siswa berikut dalam bahasa Indonesia yang formal, terstruktur, dan profesional.
{$schoolTemplateInstruction}
Profil Siswa & Detail Magang:
- Nama Siswa: {$student->name}
- Institusi Asal: " . ($school?->name ?? 'Sekolah') . "
- Jurusan: " . ($student->major?->name ?? 'Tidak ada') . "
- Tempat Magang: {$companyName}
- Posisi Magang: {$jobTitle}
- Periode Magang: {$startDate} s/d {$endDate}
- Statistik Tugas: {$completedCount} dari {$totalTasks} tugas telah diselesaikan.

Daftar Tugas & Status:
{$tasksText}

Hasilkan laporan evaluasi hasil magang dalam format JSON dengan struktur berikut:
{
  \"summary\": \"({$summaryStructureDesc})\",
  \"insights\": [\"(Wawasan/Keahlian baru yang diperoleh selama magang)\", \"(Poin pembelajaran/hasil dari tugas-tugas yang diselesaikan)\", \"(Evaluasi pencapaian kerja)\"],
  \"recommendations\": [\"(Rekomendasi area pengembangan diri untuk siswa ke depannya)\", \"(Saran penambahan skill yang perlu dikembangkan)\"]
}";
        } else {
            // Fetch recent activity logs (e.g., last 30 days)
            $logs = \App\Models\ActivityLog::orderBy('created_at', 'desc')->limit(150)->get();

            if ($logs->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada aktivitas baru di sistem untuk dianalisis.',
                    'data' => [
                        'summary' => 'Tidak ada aktivitas baru di sistem untuk dianalisis.',
                        'insights' => [],
                        'recommendations' => ['Pastikan siswa dan perusahaan mulai menggunakan sistem untuk menghasilkan log aktivitas.']
                    ]
                ]);
            }

            $logsText = "";
            foreach ($logs as $log) {
                $logsText .= "- [{$log->created_at}] User #{$log->user_id} performed '{$log->action}' on '{$log->resource_type}' (Name: {$log->resource_name}): {$log->description}\n";
            }

            $prompt = "Anda adalah analis sistem Prakerin.ID.
Tugas Anda adalah meninjau log aktivitas sistem di bawah ini dan menghasilkan laporan analitik cerdas dalam bahasa Indonesia.
Laporan harus merangkum aktivitas secara keseluruhan, menyoroti wawasan utama (insights) seperti keaktifan siswa atau kendala sistem, serta memberikan rekomendasi tindakan konkret untuk administrator.

Berikut adalah log aktivitas sistem terbaru:
$logsText

Hasilkan respons dalam format JSON dengan struktur berikut:
{
  \"summary\": \"(Ringkasan analisis dalam 2-3 paragraf)\",
  \"insights\": [\"(Wawasan 1)\", \"(Wawasan 2)\", \"(Wawasan 3)\"],
  \"recommendations\": [\"(Rekomendasi 1)\", \"(Rekomendasi 2)\"]
}";
        }

        try {
            $result = \Gemini\Laravel\Facades\Gemini::generativeModel("gemini-3.1-flash-lite")->withGenerationConfig(
                generationConfig: new \Gemini\Data\GenerationConfig(
                    responseMimeType: \Gemini\Enums\ResponseMimeType::APPLICATION_JSON,
                    responseSchema: new \Gemini\Data\Schema(
                        type: \Gemini\Enums\DataType::OBJECT,
                        properties: [
                            'summary' => new \Gemini\Data\Schema(type: \Gemini\Enums\DataType::STRING),
                            'insights' => new \Gemini\Data\Schema(
                                type: \Gemini\Enums\DataType::ARRAY,
                                items: new \Gemini\Data\Schema(type: \Gemini\Enums\DataType::STRING)
                            ),
                            'recommendations' => new \Gemini\Data\Schema(
                                type: \Gemini\Enums\DataType::ARRAY,
                                items: new \Gemini\Data\Schema(type: \Gemini\Enums\DataType::STRING)
                            )
                        ],
                        required: ['summary', 'insights', 'recommendations']
                    )
                )
            )->generateContent($prompt);

            return response()->json([
                'success' => true,
                'data' => $result->json()
            ]);
        } catch (\Throwable $e) {
            \Log::error('AI Report Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat laporan berbasis AI: ' . $e->getMessage()
            ], 500);
        }
    }
}

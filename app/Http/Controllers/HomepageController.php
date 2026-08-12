<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\HomePage\HomePageRequest;
use App\Models\CommentPrakerin;
use App\Models\Partner;
use App\Models\JobOpening;
use App\Models\Feedback;
use App\Models\Student;
use App\Models\School;
use App\Models\Company;
use DB;
use App\Models\Hompage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Log;
class HomepageController extends Controller
{
    #[OA\Get(
    path: '/homepage',
    summary: 'Get homepage data',
    tags: ['Homepage'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Homepage data retrieved successfully'
        )
    ]
)]
    public function index()
    {
        $data = Hompage::where('name', 'LIKE', '%landing%')
            ->orderBy('created_at', 'ASC')
            ->get();
        // Landing page cuma nampilin maks 12 logo per tab (company/school/
        // university) — jangan tarik SEMUA partner (bisa ribuan setelah
        // bulk import) di tiap page-load. Ambil beberapa terbaru per type
        // secukupnya buat isi tab + sedikit buffer.
        $partner = collect(['company', 'school', 'university'])
            ->flatMap(fn ($type) => Partner::where('type', $type)->latest()->limit(20)->get())
            ->values();
                $commentPrakerin = CommentPrakerin::orderBy('created_at', 'ASC')->get();
        $comments = Feedback::with(['fromUser.student', 'toUser.company'])
            ->where('to_type', 'company')
            ->orderBy('created_at', 'DESC')
            ->limit(6)
            ->get()
            ->map(function ($feedback) {
                return [
                    'id' => $feedback->id,
                    'student_name' => $feedback->fromUser?->student?->name ?? 'Student',
                    'company_name' => $feedback->toUser?->company?->name ?? 'Company',
                    'rating' => $feedback->rating,
                    'text' => $feedback->text,
                    'photo_profile' => $feedback->fromUser?->photo_profile,
                    'created_at' => $feedback->created_at,
                ];
            });
        $jobOpenings = JobOpening::with([
    'company.user',
    'company.cityRegency.province',
    'field',
    'duration',
])
    ->where('is_available', true)
    ->where('closing_date', '>=', now()->toDateString())
    ->orderBy('created_at', 'DESC')
    ->limit(6)
    ->get()
    ->map(function ($item) {
        return [
            "id" => $item->id,
            "title" => $item->title,
            "grade" => $item->grade,
            "type" => $item->type,
            "location" => $item->location,
            "qouta" => $item->qouta,
            "is_paid" => $item->is_paid,
            "is_available" => $item->is_available,
            "start_date" => $item->start_date,
            "closing_date" => $item->closing_date,
            "created_at" => $item->created_at,
            "updated_at" => $item->updated_at,
            "company" => $item->company?->makeHidden(['user', 'cityRegency']),
            "city_regency" => $item->company?->cityRegency?->makeHidden(['province']),
            "province" => $item->company?->cityRegency?->province,
            "user" => $item->company?->user,
            "field" => $item->field,
            "duration" => $item->duration,
        ];
    });

        $formatted = [];
        if ((!Auth::guard('sanctum')->user())) {
            foreach ($data as $item) {
                $formatted[$item->name] = $item->value;
            }
        } else {
            $formatted = $data;
        }


        // Real, near-real-time traction stats for the landing page (cached 60s).
        $stats = Cache::remember('landing_stats', now()->addSeconds(60), function () {
            $today = now()->toDateString();

            $siswa = (int) Student::join('schools', 'students.school_id', '=', 'schools.id')
                ->where('schools.type', 'school')->count();
            $mahasiswa = (int) Student::join('schools', 'students.school_id', '=', 'schools.id')
                ->where('schools.type', 'university')->count();

            $totalStudents = (int) Student::count();
            $placed = (int) Student::whereIn('status_magang', ['ongoing', 'completed'])->count();
            $placementRate = $totalStudents > 0 ? round($placed / $totalStudents * 100, 1) : 0;

            return [
                'schools'             => (int) School::where('type', 'school')->count(),
                'universities'        => (int) School::where('type', 'university')->count(),
                'students'            => $siswa,
                'university_students' => $mahasiswa,
                'total_students'      => $totalStudents,
                'companies'           => (int) Company::count(),
                'partners'            => (int) Partner::count(),
                'active_jobs'         => (int) JobOpening::where('is_available', true)
                    ->whereDate('closing_date', '>=', $today)->count(),
                'new_jobs_month'      => (int) JobOpening::where('created_at', '>=', now()->subDays(30))->count(),
                'placement_rate'      => $placementRate,
            ];
        });

        // Most-demanded internship categories (top fields by active openings).
        $popularCategories = Cache::remember('landing_categories', now()->addMinutes(10), function () {
            return JobOpening::select('field_id', DB::raw('COUNT(*) as total'))
                ->where('is_available', true)
                ->whereNotNull('field_id')
                ->groupBy('field_id')
                ->orderByDesc('total')
                ->with('field:id,name')
                ->limit(6)
                ->get()
                ->map(fn ($row) => [
                    'id'    => $row->field_id,
                    'name'  => $row->field?->name ?? 'Lainnya',
                    'total' => (int) $row->total,
                ])
                ->values();
        });

        return response()->json([
            'data' => [
                'homepages' => $formatted,
                'partners' => $partner,
                'comment_prakerins' => $commentPrakerin,
                'comments' => $comments,
                'job_openings' => $jobOpenings,
                'stats' => $stats,
                'popular_categories' => $popularCategories,
            ]
        ], 200);
    }


    #[OA\Get(
    path: '/homepage/about',
    summary: 'Get about page data',
    tags: ['Homepage'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'About page retrieved successfully'
        )
    ]
)]
    public function about()
    {
        $data = Hompage::where('name', 'LIKE', '%about%')->get();

        return response()->json([
        'data' => $data
    ], 200);
    }


    #[OA\Put(
    path: '/homepage',
    summary: 'Update homepage content',
    tags: ['Homepage'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'value', type: 'string')
                        ]
                    )
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Homepage updated successfully'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function update(HomePageRequest $request)
    {
        $validated = $request->validated();

        $data = $validated['data'];

        foreach ($data as $item) {
            Hompage::where('id', $item['id'])->update([
                'name' => $item['name'],
                'value' => $item['value'],
            ]);
        }

        return response()->json(['data' => true], 200);
    }
}
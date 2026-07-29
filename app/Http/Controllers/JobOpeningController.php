<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\JobOpening;
use App\Models\Duration;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

#[OA\Tag(
    name: "Job Opening",
    description: "Job Opening API"
)]

class JobOpeningController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: "/api/job-openings",
    summary: "List Job Opening",
    tags: ["Job Opening"],
    parameters: [
        new OA\QueryParameter(name: "search", required: false, schema: new OA\Schema(type: "string")),
        new OA\QueryParameter(name: "limit", required: false, schema: new OA\Schema(type: "integer")),
        new OA\QueryParameter(name: "province_id", required: false, schema: new OA\Schema(type: "array", items: new OA\Items(type: "integer"))),
        new OA\QueryParameter(name: "city_regency_id", required: false, schema: new OA\Schema(type: "array", items: new OA\Items(type: "integer"))),
        new OA\QueryParameter(name: "grade", required: false, schema: new OA\Schema(type: "array", items: new OA\Items(type: "string"))),
        new OA\QueryParameter(name: "field_id", required: false, schema: new OA\Schema(type: "array", items: new OA\Items(type: "integer"))),
        new OA\QueryParameter(name: "duration_id", required: false, schema: new OA\Schema(type: "array", items: new OA\Items(type: "integer"))),
        new OA\QueryParameter(name: "is_saved", required: false, schema: new OA\Schema(type: "boolean")),
    ],
    responses: [
        new OA\Response(response: 200, description: "Success")
    ]
)]
    public function index(Request $request) //This function is 80% overhauled as the previous one cannot load any job openings
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search', '');
        $province_id = $request->query('province_id', []);
        $city_regency_id = $request->query('city_regency_id', []);
        $grade = $request->query('grade', []);
        $field_id = $request->query('field_id', []);
        $duration_id = $request->query('duration_id', []);
        $isSaved = filter_var(request()->query('is_saved', false), FILTER_VALIDATE_BOOLEAN);

        $user = Auth::guard('sanctum')->user();

        // If user is a company, show only their job openings
        if ($user?->company) {
            $query = JobOpening::with([
                'company.user',
                'company.cityRegency.province',
                'field',
                'duration',
                'test'
            ])
                ->where('company_id', $user->company->id)
                ->where('title', 'like', "%{$search}%")
                ->when($grade, function ($query) use ($grade) {
                    $gradeArray = Arr::wrap($grade);
                    return $query->whereIn('grade', $gradeArray);
                })
                ->when($field_id, function ($query) use ($field_id) {
                    return $query->whereIn('field_id', Arr::wrap($field_id));
                })
                ->when($duration_id, function ($query) use ($duration_id) {
                    return $query->whereIn('duration_id', Arr::wrap($duration_id));
                });
        } else {
            // If user is a student, show available job openings with filters
            $query = JobOpening::with([
                'company.user',
                'company.cityRegency.province',
                'saveJobOpening' => function ($query) use ($user) {
                    $query->where('student_id', $user?->student?->id);
                },
                'field',
                'duration',
                'test'
            ])
                ->whereHas('company', function ($query) use ($province_id, $city_regency_id) {
                    if ($province_id) {
                        $query->whereHas('cityRegency', function ($query) use ($province_id) {
                            $query->whereIn('province_id', Arr::wrap($province_id));
                        });
                    }
                    if ($city_regency_id) {
                        $query->whereIn('city_regency_id', Arr::wrap($city_regency_id));
                    }
                })
                ->where('title', 'like', "%{$search}%")
                ->where('is_available', true)
                ->where('closing_date', '>=', now()->toDateString())
                ->when($grade, function ($query) use ($grade) {
                    $gradeArray = Arr::wrap($grade);
                    return $query->whereIn('grade', $gradeArray);
                })
                ->when($field_id, function ($query) use ($field_id) {
                    return $query->whereIn('field_id', Arr::wrap($field_id));
                })
                ->when($duration_id, function ($query) use ($duration_id) {
                    return $query->whereIn('duration_id', Arr::wrap($duration_id));
                })
                ->when($isSaved, function ($query) use ($user) {
                    $query->whereHas('saveJobOpening', function ($q) use ($user) {
                        $q->where('student_id', $user?->student?->id);
                    });
                });
        }

        $paginated = $query->paginate($limit);
        $paginated->getCollection()->transform(function ($item) {
            return [
                "id" => $item->id,
                "company_id" => $item->company_id,
                "field_id" => $item->field_id,
                "duration_id" => $item->duration_id,
                "title" => $item->title,
                "description" => $item->description,
                "poster" => $item->poster,
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
                'user' => $item->company?->user,
                'save_job_opening' => $item->relationLoaded('saveJobOpening') && $item->saveJobOpening->isNotEmpty() ? true : false,
                'city_regency' => $item->company?->cityRegency?->makeHidden(['province']),
                'province' => $item->company?->cityRegency?->province,
                'field' => $item->field,
                'duration' => $item->duration,
                'test' => $item->test,
            ];
        });

        return response()->json($paginated);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: "/api/job-openings",
    summary: "Create Job Opening",
    tags: ["Job Opening"],
    security: [["bearerAuth" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                "field_id",
                "duration_id",
                "title",
                "description",
                "is_paid",
                "grade",
                "type",
                "location",
                "qouta",
                "is_available",
                "start_date",
                "closing_date"
            ],
            properties: [
                new OA\Property(property: "field_id", type: "integer"),
                new OA\Property(property: "duration_id", type: "integer"),
                new OA\Property(property: "title", type: "string"),
                new OA\Property(property: "description", type: "string"),
                new OA\Property(property: "is_paid", type: "boolean"),
                new OA\Property(property: "grade", type: "string", enum: ["smk","mahasiswa","all"]),
                new OA\Property(property: "type", type: "string", enum: ["part_time","full_time"]),
                new OA\Property(property: "location", type: "string", enum: ["onsite","remote","hybrid"]),
                new OA\Property(property: "qouta", type: "integer"),
                new OA\Property(property: "is_available", type: "boolean"),
                new OA\Property(
                    property: "tests",
                    type: "array",
                    items: new OA\Items(type: "integer")
                ),
                new OA\Property(property: "start_date", type: "string", format: "date"),
                new OA\Property(property: "closing_date", type: "string", format: "date"),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: "Created"),
        new OA\Response(response: 400, description: "Validation Error")
    ]
)]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'field_id' => 'required|exists:fields,id',
            'duration_id' => 'required|exists:durations,id',
            'title' => 'required|string|max:255',
            'description' => 'required',
            'is_paid' => 'required|boolean',
            'grade' => 'required|in:smk,mahasiswa,all',
            'type' => 'required|in:part_time,full_time',
            'location' => 'required|in:onsite,remote,hybrid',
            'qouta' => 'required|integer|min:1',
            'is_available' => 'required|boolean',
            'tests' => 'array',
            'start_date' => 'required|date',
            'closing_date' => 'required|date',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 400));
        }


        $data = $validator->validated();
        $data['company_id'] = auth()->user()->company->id;
        // return response()->json($data['company_id'], 400);
        // Default: 14 hari setelah start_date
        $duration = Duration::where('id', $data['duration_id'])->where('is_accepted', true)->first();
        if (!$duration) { //added handler if duration adding fail
            throw new HttpResponseException(response()->json([
                'errors' => 'Invalid duration'
            ], 400));
        }
        $startDate = \Carbon\Carbon::parse($data['start_date']);
        // Jika request mengandung 'duration_type', gunakan 3 bulan jika '3_month', selain itu 14 hari
        if ($duration->duration_unit == 'month') {
            $data['end_date'] = $startDate->copy()->addMonths($duration->duration_value);
        } else if ($duration->duration_unit == 'day') {
            $data['end_date'] = $startDate->copy()->addDays($duration->duration_value);
        } else if ($duration->duration_unit == 'year') {
            $data['end_date'] = $startDate->copy()->addYears($duration->duration_value);
        }

        // Handle poster upload
        if ($request->hasFile('poster')) {
            $file = $request->file('poster');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('job-opening-posters', $filename, 'public');
            $data['poster'] = $filename;
        }

        $jobOpening = JobOpening::create($data);

        if (!empty($data['tests'])) { //added handler if tests return empty (remove if actually required)
            $jobOpening->test()->attach($data['tests']);
        }

        return response()->json([
            'data' => $jobOpening->load('test') //changed 'with' to 'load' because 'with' is builder not instance
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: "/api/job-openings/{id}",
    summary: "Detail Job Opening",
    tags: ["Job Opening"],
    parameters: [
        new OA\PathParameter(
            name: "id",
            required: true,
            schema: new OA\Schema(type: "integer")
        )
    ],
    responses: [
        new OA\Response(response: 200, description: "Success"),
        new OA\Response(response: 404, description: "Job Opening not found")
    ]
)]
    public function show(string $id)
    {
        $jobOpening = JobOpening::with(
            [
                'company.user',
                "company.cityRegency.province",
                'field',
                'duration',
                'test',
                'saveJobOpening' => function ($query) {
                    if (!Auth::guard('sanctum')->user()?->student()) {
                    }
                }
            ]
        )->find($id);

        $cityRegency = $jobOpening->company->cityRegency;


        if (!$jobOpening) {
            throw new HttpResponseException(response()->json(['errors' => 'Job opening not found'], 404));
        }

        $jobOpening["city_regency"] = $cityRegency ? $cityRegency->makeHidden("province") : null;
        $jobOpening["province"] = $cityRegency?->province;
        $jobOpening["company"] = $jobOpening->company->makeHidden(['user', 'cityRegency']);
        $jobOpening["user"] = $jobOpening->company->user;
        $jobOpening["start_date"] = \Carbon\Carbon::parse($jobOpening->start_date)->toDateString();
        $jobOpening["closing_date"] = \Carbon\Carbon::parse($jobOpening->closing_date)->toDateString();
        $isSaved = $jobOpening->saveJobOpening->isNotEmpty() ? true : false;
        unset($jobOpening["saveJobOpening"]);

        $jobOpening['save_job_opening'] = $isSaved;

        return response()->json([
            'data' => $jobOpening,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: "/api/job-openings/{id}",
    summary: "Update Job Opening",
    tags: ["Job Opening"],
    security: [["bearerAuth" => []]],
    parameters: [
        new OA\PathParameter(
            name: "id",
            required: true,
            schema: new OA\Schema(type: "integer")
        )
    ],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "field_id", type: "integer"),
                new OA\Property(property: "duration_id", type: "integer"),
                new OA\Property(property: "title", type: "string"),
                new OA\Property(property: "description", type: "string"),
                new OA\Property(property: "is_paid", type: "boolean"),
                new OA\Property(property: "grade", type: "string"),
                new OA\Property(property: "type", type: "string"),
                new OA\Property(property: "qouta", type: "integer"),
                new OA\Property(property: "is_available", type: "boolean"),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Updated"),
        new OA\Response(response: 404, description: "Not Found")
    ]
)]  
    public function update(Request $request, string $id)
    {
        $jobOpening = JobOpening::find($id);
        if (!$jobOpening) {
            throw new HttpResponseException(response()->json(['errors' => 'Job opening not found.'], 404));
        }

        $companyId = auth()->user()->company?->id;
        if (!$companyId) {
            throw new HttpResponseException(response()->json(['errors' => 'Company profile not found.'], 403));
        }

        if ($jobOpening->company_id !== $companyId) {
            throw new HttpResponseException(response()->json(['errors' => 'Forbidden.'], 403));
        }

        $validator = Validator::make($request->all(), [
            'field_id' => 'sometimes|required|exists:fields,id',
            'duration_id' => 'sometimes|required|exists:durations,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required',
            'is_paid' => 'sometimes|required|boolean',
            'grade' => 'sometimes|required|in:smk,mahasiswa,all',
            'type' => 'sometimes|required|in:part_time,full_time',
            'location'    => 'sometimes|required|in:onsite,remote,hybrid',
            'qouta' => 'sometimes|required|integer|min:1',
            'is_available' => 'sometimes|required|boolean',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 400));
        }

        $data = $validator->validated();

        // Handle poster upload
        if ($request->hasFile('poster')) {
            // Delete old poster if exists
            if ($jobOpening->poster && Storage::disk('public')->exists("job-opening-posters/{$jobOpening->poster}")) {
                Storage::disk('public')->delete("job-opening-posters/{$jobOpening->poster}");
            }
            $file = $request->file('poster');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('job-opening-posters', $filename, 'public');
            $data['poster'] = $filename;
        }

        $jobOpening->update($data);
        $jobOpening->save();

        if ($request->has('tests')) {
            $jobOpening->test()->sync($request->input('tests', []));
        }

        return response()->json([
            'data' => $jobOpening->load('tests') 
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: "/api/job-openings/{id}",
    summary: "Delete Job Opening",
    tags: ["Job Opening"],
    security: [["bearerAuth" => []]],
    parameters: [
        new OA\PathParameter(
            name: "id",
            required: true,
            schema: new OA\Schema(type: "integer")
        )
    ],
    responses: [
        new OA\Response(response: 200, description: "Deleted"),
        new OA\Response(response: 404, description: "Not Found")
    ]
)]
    public function destroy(string $id)
    {
        $jobOpening = JobOpening::find($id);
        if (!$jobOpening) {
            throw new HttpResponseException(response()->json(['errors' => 'Job opening not found.'], 404));
        }

        $companyId = auth()->user()->company?->id;
        if (!$companyId) {
            throw new HttpResponseException(response()->json(['errors' => 'Company profile not found.'], 403));
        }

        if ($jobOpening->company_id !== $companyId) {
            throw new HttpResponseException(response()->json(['errors' => 'Forbidden.'], 403));
        }

        // Delete poster file if exists
        if ($jobOpening->poster && Storage::disk('public')->exists("job-opening-posters/{$jobOpening->poster}")) {
            Storage::disk('public')->delete("job-opening-posters/{$jobOpening->poster}");
        }

        $jobOpening->delete();

        return response()->json([
            'data' => 'Job opening deleted successfully'
        ], 200);
    }


    #[OA\Get(
    path: "/api/job-openings/count",
    summary: "Count Job Opening",
    tags: ["Job Opening"],
    security: [["bearerAuth" => []]],
    responses: [
        new OA\Response(response: 200, description: "Success")
    ]
)]
    public function count(Request $request)
    {
        $counts = JobOpening::when($request->user()->tokenCan("company-access"), function ($query) use ($request) {
            $query->where("company_id", $request->user()->company->id);
        })
            ->selectRaw('is_available, COUNT(*) as total')
            ->groupBy('is_available')
            ->pluck('total', 'is_available')
            ->toArray();

        // siapkan default biar selalu ada key true/false meskipun count = 0
        $final = [
            'true' => $counts[1] ?? 0, // di DB boolean biasanya 1/0
            'false' => $counts[0] ?? 0,
            'total' => array_sum($counts),
        ];
        return response()->json([
            'data' => $final
        ]);
    }
}
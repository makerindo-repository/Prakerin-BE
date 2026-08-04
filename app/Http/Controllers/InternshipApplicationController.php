<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Internship;
use App\Models\InboxItem;
use App\Models\InternshipApplication;
use App\Services\NotificationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Log;


class InternshipApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/internship-applications',
    summary: 'Get internship applications',
    tags: ['Internship Application'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Success')
    ]
)]
    public function index()
    {
        $limit = request()->query('limit', 10);
        $status = request()->query('status', null);


        if (auth()->user()->tokenCan('company-access')) {

            $search = request()->query('search', '');

            $internshipApplications = InternshipApplication::with([
                'curriculumVitae.student.user',
                'curriculumVitae.student.school'
            ])
                ->whereHas('jobOpening', function ($query) {
                    $query->where('company_id', auth()->user()->company->id);
                })
                ->whereHas('curriculumVitae.student', function ($query) use ($search) {
                    $query->where('name', 'like', "%$search%");
                })
                ->when($status !== null, function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->paginate($limit);

            $internshipApplications->getCollection()->transform(function ($item) {
                $student = $item->curriculumVitae->student;
                $student->makeHidden(['user', 'school']);

                return [
                    'id' => $item->id,
                    'curriculum_vitae' => [
                        'id' => $item->curriculumVitae->id,
                        'name' => $item->curriculumVitae->name,
                    ],
                    'job_opening_id' => $item->job_opening_id,
                    'status' => $item->status,
                    'cover_letter' => $item->cover_letter,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                    'student' => $student,
                    'user' => $student->user,
                    'school' => $student->school,
                    'major' => $student->major?->name,

                ];

            });

            return response()->json($internshipApplications);

        }



        $internshipApplications = InternshipApplication::with('jobOpening.company.user', 'test')
            ->whereHas('curriculumVitae', fn($query) => $query->where('student_id', auth()->user()->student->id))
            ->paginate($limit);


        $data = $internshipApplications->getCollection()->map(function ($app) {
            return [
                'id' => $app->id,
                'job_opening_id' => $app->job_opening_id,
                'status' => $app->status,
                'cover_letter' => $app->cover_letter,
                'created_at' => $app->created_at,
                'updated_at' => $app->updated_at,

                'curriculum_vitae' => [
                    'id' => $app->curriculumVitae->id,
                    'name' => $app->curriculumVitae->name,
                ],

                'job_opening' => $app->jobOpening ? [
                    'id' => $app->jobOpening->id,
                    'title' => $app->jobOpening->title,
                    'description' => $app->jobOpening->description,
                    'duration' => $app->jobOpening->duration,
                    'type' => $app->jobOpening->type,
                    'qouta' => $app->jobOpening->qouta,
                    'is_paid' => $app->jobOpening->is_paid,
                    'is_available' => $app->jobOpening->is_available,
                ] : null,

                'test' => $app->test,

                'company' => $app->jobOpening?->company ? [
                    'id' => $app->jobOpening->company->id,
                    'name' => $app->jobOpening->company->name,
                    'address' => $app->jobOpening->company->address,
                    'phone_number' => $app->jobOpening->company->phone_number,
                    'is_verified' => $app->jobOpening->company->is_verified,
                ] : null,

                'user' => $app->jobOpening?->company?->user ? [
                    'id' => $app->jobOpening->company->user->id,
                    'username' => $app->jobOpening->company->user->username,
                    'email' => $app->jobOpening->company->user->email,
                    'role' => $app->jobOpening->company->user->role,
                ] : null,
                'city_regency' => $app->jobOpening?->company?->cityRegency?->makeHidden('province'),
                'province' => $app->jobOpening?->company?->cityRegency?->province,
            ];
        });

        $internshipApplications->setCollection($data);

        return response()->json($internshipApplications);
    }
    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/internship-applications',
        summary: 'Apply internship',
        tags: ['Internship Application'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['curriculum_vitae_id','job_opening_id','cover_letter'],
                properties: [
                    new OA\Property(property: 'curriculum_vitae_id', type: 'integer'),
                    new OA\Property(property: 'job_opening_id', type: 'integer'),
                    new OA\Property(property: 'cover_letter', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Application created'),
            new OA\Response(response: 400, description: 'Validation failed')
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'curriculum_vitae_id' => 'required|exists:curriculum_vitaes,id',
            'job_opening_id' => 'required|exists:job_openings,id',
            'cover_letter' => 'required',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                ['errors' => $validator->errors()],
                400
            ));
        }

        $data = $validator->validated();

        $user = $request->user();

        $findIntershipApplicationWithSameStudentCount = InternshipApplication::where("job_opening_id", $data['job_opening_id'])
            ->whereHas('curriculumVitae.student', function ($query) use ($user) {
                $query->where('id', $user->student->id);
            })
            ->count();

        if ($findIntershipApplicationWithSameStudentCount !== 0) {
            throw new HttpResponseException(response()->json([
                'errors' => "Anda tidak bisa melamar 2 kali di lowonngan magang yang sama!",
                's' => $findIntershipApplicationWithSameStudentCount,
            ], 400));
        }

        // Batasi jumlah lamaran yang masih "menunggu" (belum accepted/rejected)
        // sesuai pengaturan admin di Pengaturan > Program Magang, supaya siswa
        // gak nge-spam lamaran ke banyak perusahaan sekaligus.
        $maxConcurrent = (int) \App\Models\Setting::getVal('max_concurrent_applications', 3);
        if ($maxConcurrent > 0) {
            $pendingApplicationsCount = InternshipApplication::where('status', 'in_progress')
                ->whereHas('curriculumVitae.student', function ($query) use ($user) {
                    $query->where('id', $user->student->id);
                })
                ->count();

            if ($pendingApplicationsCount >= $maxConcurrent) {
                throw new HttpResponseException(response()->json([
                    'errors' => "Anda sudah mencapai batas maksimal ({$maxConcurrent}) lamaran yang masih menunggu. Tunggu hasil lamaran sebelumnya sebelum melamar lagi.",
                ], 400));
            }
        }

        $internshipApplication = InternshipApplication::create($data);

        $test = $internshipApplication->jobOpening->test->pluck('pivot.test_id')->toArray();

        $internshipApplication->test()->attach($test);

        // Notify the company about the new application
        try {
            $companyUser = $internshipApplication->jobOpening?->company?->user;
            if ($companyUser) {
                $studentName = $user->student?->name ?? $user->username;
                $jobTitle    = $internshipApplication->jobOpening?->title ?? 'Lowongan';
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

                app(NotificationService::class)->notify(
                    userId      : $companyUser->id,
                    title       : "Lamaran Baru: {$jobTitle}",
                    content     : "{$studentName} telah melamar untuk posisi '{$jobTitle}'. Segera tinjau lamaran ini.",
                    type        : 'new_application',
                    actionUrl   : "{$frontendUrl}/dashboard/industry/lamaran/{$internshipApplication->id}",
                    relatedType : 'InternshipApplication',
                    relatedId   : $internshipApplication->id,
                    senderId    : $user->id
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[InternshipApplicationController] Company notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'data' => $test
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Patch(
    path: '/internship-applications/{idInternshipApplication}/tests/{idTest}',
    summary: 'Update test passed status',
    tags: ['Internship Application'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'idInternshipApplication',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        ),
        new OA\Parameter(
            name: 'idTest',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    responses: [
        new OA\Response(response: 201, description: 'Updated successfully')
    ]
)]
    public function updateTestPassed($idInternshipApplication, $idTest)
    {
        $internshipApplication = InternshipApplication::find($idInternshipApplication);
        if (!$internshipApplication) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Internship application not found.'
            ], 404));
        }

        $testItem = $internshipApplication->test()->where('test_id', $idTest)->first();
        if (!$testItem) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Test not found for this application.'
            ], 404));
        }

        $testIsPassed = request()->has('is_passed')
            ? request()->boolean('is_passed')
            : !$testItem->pivot->is_passed;

        $internshipApplication->test()->updateExistingPivot($idTest, ['is_passed' => $testIsPassed]);
        Log::info($internshipApplication->test);

        return response()->json([
            'data' => true
        ], 200);
    }


    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: '/internship-applications/{id}',
    summary: 'Get internship application detail',
    tags: ['Internship Application'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    responses: [
        new OA\Response(response: 200, description: 'Success'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Not found')
    ]
)]
    public function show(string $id)
    {
        $internshipApplication = InternshipApplication::
            with(['curriculumVitae.student.user', 'curriculumVitae.student.major', 'test'])
            ->find($id);

        if (!$internshipApplication) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship Application not found.'],
                404
            ));
        }

        $companyId = auth()->user()->company?->id;
        if (!$companyId) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Company profile not found.'],
                403
            ));
        }

        if ($internshipApplication->jobOpening?->company_id !== $companyId) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Forbidden.'],
                403
            ));
        }

        $internshipApplication = [
            'cover_letter' => $internshipApplication->cover_letter,
            'status' => $internshipApplication->status,
            'student' => $internshipApplication->curriculumVitae->student->makeHidden(['user']),
            'user' => $internshipApplication->curriculumVitae->student->user,
            'major' => $internshipApplication->curriculumVitae->student->major,
            'curriculum_vitae_id' => $internshipApplication->curriculum_vitae_id,
            'job_opening' => $internshipApplication->jobOpening->makeHidden(['test']),
            'test' => $internshipApplication->test,
        ];

        return response()->json([
            'data' => $internshipApplication
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Post(
    path: '/internship-applications/{id}',
    summary: 'Accept or reject internship application',
    tags: ['Internship Application'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['status','file'],
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        enum: ['accepted','rejected']
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Updated'),
        new OA\Response(response: 400, description: 'Validation failed'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Not found')
    ]
)]
    public function update(Request $request, string $id)
    {
        $internshipApplication = InternshipApplication::find($id);

        if (!$internshipApplication) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship Application not found.'],
                404
            ));
        }

        $jobOpening = $internshipApplication->jobOpening;

        $companyId = auth()->user()->company?->id;
        if (!$companyId) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Company profile not found.'],
                403
            ));
        }

        if ($jobOpening?->company_id !== $companyId) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Forbidden.'],
                403
            ));
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,rejected',
            'file' => 'required|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                ['errors' => $validator->errors()],
                400
            ));
        }

        $data = $validator->validated();

        if ($data['status'] === 'accepted') {
            $existingInternship = Internship::where('internship_application_id', $internshipApplication->id)->first();
            if (!$existingInternship) {
                $internship = new Internship();
                $internship->internship_application_id = $internshipApplication->id;
                $internship->start_date = $jobOpening->start_date;
                $internship->end_date = $jobOpening->end_date;
                $internship->student_id = $internshipApplication->curriculumVitae->student_id;
                $internship->company_id = $request->user()->company->id;
                $internship->save();
            }

            if ($internshipApplication->curriculumVitae?->student) {
                $internshipApplication->curriculumVitae->student->status_magang = "ongoing";
                $internshipApplication->curriculumVitae->student->save();
            }
        }
        $studentUser  = $internshipApplication->curriculumVitae?->student?->user;
        $statusIndo   = $data['status'] === 'accepted' ? 'DITERIMA 🎉' : 'DITOLAK';
        $jobTitle     = $internshipApplication->jobOpening?->title ?? 'Lowongan';
        $frontendUrl  = config('app.frontend_url', 'http://localhost:3000');

        $pdf = $request->file('file');
        if ($pdf && $studentUser?->email) {
            try {
                $pdfContent = file_get_contents($pdf->getRealPath());
                $email = $studentUser->email;
                Mail::send([], [], function ($message) use ($email, $pdf, $pdfContent) {
                    $message->to($email)
                        ->subject('Update Status Lamaran Magang')
                        ->html('<p>Halo, status lamaran magang Anda telah di-update!</p>');
                    $message->attachData($pdfContent, $pdf->getClientOriginalName(), [
                        'mime' => 'application/pdf',
                    ]);
                });
            } catch (\Throwable $e) {
                Log::warning('[InternshipApplicationController] Email sending failed: ' . $e->getMessage());
            }
        }

        // Inbox + WhatsApp notification for student
        try {
            app(NotificationService::class)->notify(
                userId      : $studentUser->id,
                title       : "Status Lamaran: {$statusIndo}",
                content     : "Lamaran Anda untuk posisi '{$jobTitle}' telah diperbarui menjadi {$statusIndo}. Silakan cek detail lamaran untuk informasi selengkapnya.",
                type        : 'application_status',
                actionUrl   : "{$frontendUrl}/dashboard/student/lamaran/{$internshipApplication->id}",
                relatedType : 'InternshipApplication',
                relatedId   : $internshipApplication->id
            );
        } catch (\Throwable $e) {
            Log::warning('[InternshipApplicationController] Student notification failed: ' . $e->getMessage());
        }

        $internshipApplication->status = $data['status'];
        $internshipApplication->save();

        return response()->json([
            'data' => true
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/internship-applications/{id}',
    summary: 'Delete internship application',
    tags: ['Internship Application'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    responses: [
        new OA\Response(response: 200, description: 'Deleted'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Not found')
    ]
)]
    public function destroy(string $id)
    {
        $internshipApplication = InternshipApplication::find($id);

        if (!$internshipApplication) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship Application not found.'],
                404
            ));
        }

        $companyId = auth()->user()->company?->id;
        if (!$companyId) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Company profile not found.'],
                403
            ));
        }

        if ($internshipApplication->jobOpening?->company_id !== $companyId) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Forbidden.'],
                403
            ));
        }

        $internshipApplication->delete();

        return response()->json([
            'messages' => 'Internship Application deleted successfully.'
        ], 200);
    }

    
    #[OA\Get(
    path: '/internship-applications/count',
    summary: 'Get internship application statistics',
    tags: ['Internship Application'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Statistics retrieved successfully'
        )
    ]
)]
    public function count()
    {

        $counts = InternshipApplication::whereHas(
            'curriculumVitae',
            fn($query) =>
            $query->where('student_id', auth()->user()->student->id)
        )
            ->selectRaw("status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $internshipApplicationsCount = $counts->sum();
        $acceptedCount = $counts['accepted'] ?? 0;
        $rejectedCount = $counts['rejected'] ?? 0;
        $inProgressCount = $counts['in_progress'] ?? 0;

        return response()->json([
            'data' => [

                'total' => $internshipApplicationsCount,
                'accepted' => $acceptedCount,
                'rejected' => $rejectedCount,
                'in_progress' => $inProgressCount,
            ]
        ]);
    }
}
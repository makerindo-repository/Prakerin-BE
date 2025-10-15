<?php

namespace App\Http\Controllers;

use App\Models\Internship;
use App\Models\InternshipApplication;
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

                'job_opening' => [
                    'id' => $app->jobOpening->id,
                    'title' => $app->jobOpening->title,
                    'description' => $app->jobOpening->description,
                    'duration' => $app->jobOpening->duration,
                    'type' => $app->jobOpening->type,
                    'qouta' => $app->jobOpening->qouta,
                    'is_paid' => $app->jobOpening->is_paid,
                    'is_available' => $app->jobOpening->is_available,
                ],

                'test' => $app->test,

                'company' => [
                    'id' => $app->jobOpening->company->id,
                    'name' => $app->jobOpening->company->name,
                    'address' => $app->jobOpening->company->address,
                    'phone_number' => $app->jobOpening->company->phone_number,
                    'is_verified' => $app->jobOpening->company->is_verified,
                ],

                'user' => [
                    'id' => $app->jobOpening->company->user->id,
                    'username' => $app->jobOpening->company->user->username,
                    'email' => $app->jobOpening->company->user->email,
                    'role' => $app->jobOpening->company->user->role,
                ],
                'city_regency' => $app->jobOpening->company->cityRegency->makeHidden('province'),
                'province' => $app->jobOpening->company->cityRegency->province,
            ];
        });

        $internshipApplications->setCollection($data);

        return response()->json($internshipApplications);
    }
    /**
     * Store a newly created resource in storage.
     */
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

        $internshipApplication = InternshipApplication::create($data);

        $test = $internshipApplication->jobOpening->test->pluck('pivot.test_id')->toArray();

        $internshipApplication->test()->attach($test);

        // dd($test);

        return response()->json([
            'data' => $test
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateTestPassed($idInternshipApplication, $idTest)
    {
        $internshipApplication = InternshipApplication::find($idInternshipApplication);
        $testIsPassed = $internshipApplication->test()->where('test_id', $idTest)->first()->pivot->is_passed;

        $internshipApplication->test()->updateExistingPivot($idTest, ['is_passed' => !$testIsPassed]);
        Log::info($internshipApplication->test);

        return response()->json([
            'data' => true
        ], 201);
    }


    /**
     * Display the specified resource.
     */
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


        if ($internshipApplication->jobOpening->company_id !== auth()->user()->company->id) {
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
    public function update(Request $request, string $id)
    {
        $internshipApplication = InternshipApplication::find($id);
        $jobOpeing = $internshipApplication->jobOpening;

        if (!$internshipApplication) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship Application not found.'],
                404
            ));
        }

        if ($internshipApplication->jobOpening->company_id !== auth()->user()->company->id) {
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
            $internship = new Internship();

            $internship->internship_application_id = $internshipApplication->id;

            $internship->start_date = $jobOpeing->start_date;
            $internship->end_date = $jobOpeing->end_date;
            $internship->student_id = $internshipApplication->curriculumVitae->student_id;
            $internship->company_id = $request->user()->company->id;

            $internship->save();

            $internshipApplication->curriculumVitae->student->status = "ongoing";
            $internshipApplication->curriculumVitae->student->save();
        }
        $email = $internshipApplication->curriculumVitae->student->user->email;

        $pdf = $request->file('file');
        $pdfContent = file_get_contents($pdf->getRealPath()); // ambil isi file dari memori

        Mail::send([], [], function ($message) use ($email, $pdf, $pdfContent) {
            $message->to($email)
                ->subject('Dokumen PDF Anda')
                ->html('<p>Halo, ini dokumen PDF yang Anda kirimkan!</p>')
                ->attachData($pdfContent, $pdf->getClientOriginalName(), [
                    'mime' => 'application/pdf',
                ]);
        });

        $internshipApplication->status = $data['status'];
        $internshipApplication->save();

        return response()->json([
            'data' => true
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $internshipApplication = InternshipApplication::find($id);

        if (!$internshipApplication) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship Application not found.'],
                404
            ));
        }

        if ($internshipApplication->jobOpening->company_id !== auth()->user()->company->id) {
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

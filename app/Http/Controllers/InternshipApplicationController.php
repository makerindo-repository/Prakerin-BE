<?php

namespace App\Http\Controllers;

use App\Models\InternshipApplication;
use Illuminate\Http\Request;

class InternshipApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $limit = request()->query('limit', 10);
        $internshipApplications = InternshipApplication::with('jobOpening.company.user')
            ->whereHas('curriculumVitae', fn($query) => $query->where('student_id', auth()->user()->student->id))
            ->paginate($limit);


        $data = $internshipApplications->getCollection()->map(function ($app) {
            return [
                'id' => $app->id,
                'curriculum_vitae_id' => $app->curriculum_vitae_id,
                'job_opening_id' => $app->job_opening_id,
                'status' => $app->status,
                'step' => $app->step,
                'created_at' => $app->created_at,
                'updated_at' => $app->updated_at,

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
            ];
        });

        // ganti collection hasil paginate dengan data yg sudah dimap
        $internshipApplications->setCollection($data);

        return response()->json($internshipApplications);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
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

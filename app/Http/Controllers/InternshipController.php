<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Certificate;
use App\Models\Internship;
use App\Models\User;
use App\Models\Student;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InternshipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/internships',
    summary: 'Get internship list',
    tags: ['Internship'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Success'
        )
    ]
)]
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);

        $internships = Internship::paginate($limit);

        return response()->json($internships);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return response()->json(['errors' => 'Not implemented yet.'], 501);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: '/internships/{id}',
    summary: 'Get internship by user id',
    tags: ['Internship'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(response: 200, description: 'Success'),
        new OA\Response(response: 404, description: 'Student or Internship not found')
    ]
)]
    public function show(string $id)
    {

        //Look for the student based on user id
        $student = Student::where('user_id', $id)->first();
        if (!$student) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Student not found.'],
                404
            ));
        }

        //Then look for internship based on student id
        $internship = Internship::where('student_id', $student->id)->first();
        if (!$internship) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship not found.'],
                404
            ));
        }
        
        //Then return the data
        return response()->json([
            'data' => [
                'id' => $internship->id,
                'start_date' => $internship->start_date,
                'end_date' => $internship->end_date,
                'is_completed' => $internship->is_completed
            ]
        ]);

    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/internships/{id}',
    summary: 'Update internship',
    tags: ['Internship'],
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
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'start_date',
                    type: 'string',
                    format: 'date'
                ),
                new OA\Property(
                    property: 'end_date',
                    type: 'string',
                    format: 'date'
                ),
                new OA\Property(
                    property: 'is_completed',
                    type: 'boolean'
                ),
                new OA\Property(
                    property: 'role_id',
                    type: 'integer'
                )
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Updated successfully'),
        new OA\Response(response: 400, description: 'Validation Error'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Internship not found')
    ]
)]
    public function update(Request $request, string $id)
    {
        $internship = Internship::find($id);

        if (!$internship) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship not found.'],
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

        if ($internship->internshipApplication?->jobOpening?->company_id !== $companyId) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Forbidden.'],
                403
            ));
        }
        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_completed' => 'sometimes|required|boolean',
            'role_id' => 'sometimes|required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                ['errors' => $validator->errors()],
                400
            ));
        }

        $data = $validator->validated();



        if (isset($data['is_completed']) && $data['is_completed'] === true) {
            if (!$internship->is_completed) {
                $certificate = new Certificate();
                $certificate->internship_id = $internship->id;
                $certificate->save();
                if ($internship->student && $internship->student->user) {
                    auth()->user()->rated()->syncWithoutDetaching([$internship->student->user->id]);
                }
            }
        }


        $internship->update($data);
        $internship->save();

        $internship->makeHidden('internshipApplication');

        return response()->json([
            'data' => $internship,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/internships/{id}',
    summary: 'Delete internship',
    tags: ['Internship'],
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
        new OA\Response(response: 200, description: 'Deleted successfully'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Internship not found')
    ]
)]
    public function destroy(string $id)
    {
        $internship = Internship::find($id);

        if (!$internship) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship not found.'],
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

        if ($internship->internshipApplication?->jobOpening?->company_id !== $companyId) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Forbidden.'],
                403
            ));
        }

        $internship->delete();

        return response()->json([
            'data' => 'Internship deleted successfully.',
        ], 200);
    }


    #[OA\Get(
    path: '/internships/count',
    summary: 'Count active internships',
    tags: ['Internship'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Count retrieved successfully'
        )
    ]
)]
    public function count(Request $request)
    {
        $companyId = $request->user()->company?->id;
        if (!$companyId) {
            return response()->json(['data' => 0]);
        }

        $count = Internship::where('is_completed', false)
            ->whereHas('internshipApplication.jobOpening', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->count();


        return response()->json([
            'data' => $count
        ]);
    }
}

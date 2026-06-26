<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\SaveJobOpening;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaveJobOpeningController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/save-job-opening',
    summary: 'Get saved job openings',
    tags: ['Save Job Opening'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Pagination limit',
            schema: new OA\Schema(
                type: 'integer',
                default: 10
            )
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Saved job openings retrieved successfully'
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthorized'
        )
    ]
)]
    public function index()
    {
        $limit = request()->query('limit', 10);

        $saveJobOpenings = SaveJobOpening::with('jobOpening.company.user')
            ->where('student_id', auth()->user()->student->id)
            ->paginate($limit);

        // ubah struktur biar user sejajar dengan company
        $saveJobOpenings->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'student_id' => $item->student_id,
                'job_opening_id' => $item->job_opening_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,

                'job_opening' => [
                    'id' => $item->jobOpening->id,
                    'title' => $item->jobOpening->title,
                    'description' => $item->jobOpening->description,
                    'grade' => $item->jobOpening->grade,
                    'type' => $item->jobOpening->type,
                    'is_paid' => $item->jobOpening->is_paid,
                    'is_available' => $item->jobOpening->is_available,
                ],

                'company' => [
                    'id' => $item->jobOpening->company->id,
                    'name' => $item->jobOpening->company->name,
                    'address' => $item->jobOpening->company->address,
                    'phone_number' => $item->jobOpening->company->phone_number,
                ],

                'user' => [
                    'id' => $item->jobOpening->company->user->id,
                    'username' => $item->jobOpening->company->user->username,
                    'email' => $item->jobOpening->company->user->email,
                    'role' => $item->jobOpening->company->user->role,
                ]
            ];
        });

        return response()->json($saveJobOpenings);

    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/save-job-opening',
    summary: 'Save or unsave job opening',
    tags: ['Save Job Opening'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['job_opening_id'],
            properties: [
                new OA\Property(
                    property: 'job_opening_id',
                    type: 'integer',
                    example: 1
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Job opening saved successfully'
        ),
        new OA\Response(
            response: 200,
            description: 'Saved job opening removed successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthorized'
        )
    ]
)]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_opening_id' => 'required|exists:job_openings,id',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $existing = SaveJobOpening::where('student_id', auth()->user()->student->id)
            ->where('job_opening_id', $request->job_opening_id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'message' => 'Save Job Opening deleted successfully.'
            ], 200);

        }

        $saveJobOpening = SaveJobOpening::create([
            'student_id' => auth()->user()->student->id,
            'job_opening_id' => $request->job_opening_id,
        ]);

        return response()->json([
            'data' => $saveJobOpening
        ], 201);
    }

}

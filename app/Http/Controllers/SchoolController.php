<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SchoolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/school',
    summary: 'Get school list',
    tags: ['School'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'School list retrieved successfully'
        )
    ]
)]
    public function index()
    {
        $schools = School::with('user')->get();

        return response()->json(["data" => $schools], 200);
    }

    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/school/name',
    summary: 'Get school names',
    tags: ['School'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'School names retrieved successfully'
        )
    ]
)]
    public function schoolName()
    {
        $schools = School::get(['id', 'name']);
        return response()->json(['data' => $schools]);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/school',
    summary: 'Create school',
    tags: ['School'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['name', 'phone_number', 'address'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'SMK Negeri 1 Jakarta'
                    ),
                    new OA\Property(
                        property: 'phone_number',
                        type: 'string',
                        example: '02112345678'
                    ),
                    new OA\Property(
                        property: 'address',
                        type: 'string',
                        example: 'Jl. Sudirman No.1'
                    ),
                    new OA\Property(
                        property: 'is_verified',
                        type: 'boolean',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'logo',
                        type: 'string',
                        format: 'binary',
                        nullable: true
                    )
                ]
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'School created successfully'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function store(Request $request, School $school)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|numeric',
            'address' => 'required|string',
            // 'user_id' => 'required|max:20',
            'is_verified' => 'boolean',
            'logo' => 'image'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $data = $request->only('name', 'date_of_birth', 'gender', 'phone_number', 'address', 'school_id', 'user_id');

        try {
            //code...
            $result = $school::factory()->create($data);
        } catch (\Throwable $th) {
            return response()->json(["error" => $th], 500);
        }

        return response()->json(['data' => $result], 200);
    }



    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: '/school/{id}',
    summary: 'Get school detail',
    tags: ['School'],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'School retrieved successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'School not found'
        )
    ]
)]
    public function show(string $id, )
    {
        $school = School::with('user')->find($id);

        if (!$school) {
            return response()->json(["error" => "School not found"], 404);
        }

        return response()->json([
            "data" => $school
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/school/{id}',
    summary: 'Update school',
    tags: ['School'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['name', 'phone_number', 'address'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'phone_number',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'address',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'is_verified',
                        type: 'boolean',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'logo',
                        type: 'string',
                        format: 'binary',
                        nullable: true
                    )
                ]
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'School updated successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'School not found'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function update(Request $request, string $id)
    {
        $school = School::with('user')->find($id);

        if (!$school) {
            return response()->json(["error" => "School not found"], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|numeric',
            'address' => 'required|string',
            // 'user_id' => 'required|max:20',
            'is_verified' => 'boolean',
            'logo' => 'image'
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $data = $request->only('name', 'date_of_birth', 'gender', 'phone_number', 'address', 'school_id');
        try {
            $school->update($data);
        } catch (\Throwable $th) {
            return response()->json(["error" => $th], 500);
        }

        return response()->json([
            'message' => 'success update data',
            'data' => $school
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/school/{id}',
    summary: 'Delete school',
    tags: ['School'],
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
        new OA\Response(
            response: 201,
            description: 'School deleted successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'School not found'
        )
    ]
)]
    public function destroy(string $id)
    {
        if (!$school = School::with('user')->find($id)) {
            return response()->json(["error" => "School not found"], 404);
        }

        try {
            $school->delete();
            return response()->json(null, 201);
        } catch (\Throwable $th) {
            return response()->json(["error" => $th], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: '/school/count',
    summary: 'Get total school count',
    tags: ['School'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'School count retrieved successfully'
        )
    ]
)]
    public function schoolCount()
    {
        $count = School::count();
        return response()->json(['data' => $count], 200);
    }

    /**
     * Get school report template.
     */
    public function getReportTemplate(Request $request)
    {
        $user = $request->user();
        $school = $user->school ?? School::where('user_id', $user->id)->first();

        if (!$school) {
            return response()->json(['message' => 'Sekolah tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => [
                'report_template' => $school->report_template ?? ''
            ]
        ], 200);
    }

    /**
     * Update school report template.
     */
    public function updateReportTemplate(Request $request)
    {
        $user = $request->user();
        $school = $user->school ?? School::where('user_id', $user->id)->first();

        if (!$school) {
            return response()->json(['message' => 'Sekolah tidak ditemukan.'], 404);
        }

        $request->validate([
            'report_template' => 'nullable|string'
        ]);

        $school->report_template = $request->input('report_template');
        $school->save();

        return response()->json([
            'message' => 'Template laporan berhasil disimpan.',
            'data' => [
                'report_template' => $school->report_template
            ]
        ], 200);
    }
}

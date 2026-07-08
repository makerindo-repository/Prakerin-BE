<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\JobPosition;
use Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JobPositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/job-positions',
    summary: 'Get job position list (catalog data for charts)',
    tags: ['JobPosition'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Search job position name',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'is_accepted',
            in: 'query',
            required: false,
            description: 'Filter accepted job positions',
            schema: new OA\Schema(type: 'boolean', default: true)
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Pagination limit',
            schema: new OA\Schema(type: 'integer', default: 10)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Job position list retrieved successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        )
    ]
)]
    public function index(Request $request)
    {
        $isAccepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');
        $limit = $request->query('limit', 10);

        if ($isAccepted === false && !$request->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            $positions = JobPosition::where('is_accepted', $isAccepted)
                ->where('name', "like", "%$search%")
                ->paginate($limit);

            return response()->json($positions, 200);
        } else {
            $positions = JobPosition::where('is_accepted', $isAccepted)
                ->where('name', "like", "%$search%")
                ->get();

            return response()->json([
                'data' => $positions,
            ], 200);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/job-positions',
    summary: 'Create job position',
    tags: ['JobPosition'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    example: 'Software Engineer'
                ),
                new OA\Property(
                    property: 'is_accepted',
                    type: 'boolean',
                    nullable: true,
                    example: true
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Job position created successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        )
    ]
)]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'is_accepted' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $data = $validator->validated();

        $position = new JobPosition();
        $position->name = $data['name'];
        if ($request->user()->tokenCan("admin-access")) {
            $position->is_accepted = $data['is_accepted'] ?? false;
        }
        $position->save();

        return response()->json([
            'data' => $position
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/job-positions/{id}',
    summary: 'Update job position',
    tags: ['JobPosition'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Job position ID',
            schema: new OA\Schema(type: 'string')
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Backend Developer'),
                new OA\Property(property: 'is_accepted', type: 'boolean', nullable: true, example: true)
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Job position updated successfully'),
        new OA\Response(response: 400, description: 'Validation Error'),
        new OA\Response(response: 404, description: 'Not found')
    ]
)]
    public function update(Request $request, string $id)
    {
        $position = JobPosition::find($id);
        if (!$position) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Job position not found'
            ], 404));
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'is_accepted' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $data = $validator->validated();
        $position->name = $data['name'] ?? $position->name;
        $position->is_accepted = $data['is_accepted'] ?? $position->is_accepted;
        $position->save();

        return response()->json(['data' => $position], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/job-positions/{id}',
    summary: 'Delete job position',
    tags: ['JobPosition'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Job position ID',
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(response: 200, description: 'Job position deleted successfully'),
        new OA\Response(response: 404, description: 'Not found')
    ]
)]
    public function destroy(string $id)
    {
        $position = JobPosition::find($id);
        if (!$position) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Job position not found'
            ], 404));
        }

        $position->delete();

        return response()->json([
            'message' => 'Job position deleted successfully'
        ], 200);
    }
}

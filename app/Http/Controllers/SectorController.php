<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\Sector\SectorCreateRequest;
use App\Http\Requests\Sector\SectorUpdateRequest;
use App\Models\Sector;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SectorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/sector',
    summary: 'Get sector list',
    tags: ['Sector'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Search sector name',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'is_accepted',
            in: 'query',
            required: false,
            description: 'Filter accepted sector',
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
            description: 'Sector list retrieved successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        )
    ]
)]
    public function index(Request $request)
    {
        $is_accepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');
        $limit = $request->query('limit', 10);

        if ($is_accepted === false && !Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {

            $sectors = Sector::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->orderBy('updated_at', 'desc')
                ->paginate($limit);

            return response()->json(
                $sectors,
                200
            );

        } else {
            $sectors = Sector::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'data' => $sectors,
            ], 200);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/sector',
    summary: 'Create sector',
    tags: ['Sector'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    example: 'Teknologi Informasi'
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
            description: 'Sector created successfully'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function store(SectorCreateRequest $request)
    {
        $data = $request->validated();

        $sector = new Sector();
        $sector->name = $data['name'];
        if ($request->user()->tokenCan("admin-access")) {
            $sector->is_accepted = $data['is_accepted'];
        }

        $sector->save();

        return response()->json([
            'data' => $sector,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/sector/{id}',
    summary: 'Update sector',
    tags: ['Sector'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Sector ID',
            schema: new OA\Schema(type: 'string')
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    nullable: true,
                    example: 'Teknik Mesin'
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
            response: 200,
            description: 'Sector updated successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Sector not found'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function update(SectorUpdateRequest $request, string $id)
    {
        $sector = Sector::find($id);

        if (!$sector) {
            throw new HttpResponseException(response([
                'errors' => 'Sector not found',
            ], 404));
        }

        $data = $request->validated();

        $sector->name = $data['name'] ?? $sector->name;
        $sector->is_accepted = $data['is_accepted'] ?? $sector->is_accepted;

        $sector->save();

        return response()->json([
            'data' => $sector,
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/sector/{id}',
    summary: 'Delete sector',
    tags: ['Sector'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Sector ID',
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Sector deleted successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Sector not found'
        )
    ]
)]
    public function destroy(string $id)
    {
        $sector = Sector::find($id);

        if (!$sector) {
            throw new HttpResponseException(response([
                'errors' => 'Sector not found',
            ], 404));
        }

        $sector->delete();

        return response()->json([
            'message' => 'Sector deleted successfully',
        ], 200);
    }
}

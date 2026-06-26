<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\Province\ProvinceCreateRequest;
use App\Http\Requests\Province\ProvinceUpdateRequest;
use App\Models\Province;
use Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProvinceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/province',
    summary: 'Get province list',
    tags: ['Province'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Search province name',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'is_accepted',
            in: 'query',
            required: false,
            description: 'Filter accepted province',
            schema: new OA\Schema(type: 'boolean', default: true)
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Pagination limit',
            schema: new OA\Schema(type: 'integer', default: 10)
        ),
        new OA\Parameter(
            name: 'is_limit',
            in: 'query',
            required: false,
            description: 'Return paginated data',
            schema: new OA\Schema(type: 'boolean', default: false)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Province list retrieved successfully'
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
        $isLimit = filter_var($request->query('is_limit', false), FILTER_VALIDATE_BOOLEAN);

        if ($is_accepted === false && !Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }


        Log::info("Province Controller limit = $limit");


        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            $provinces = Province::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->paginate($limit);
            return response()->json(
                $provinces,
                200
            );
        } else {
            if ($isLimit) {
            $provinces = Province::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->paginate($limit);
            return response()->json($provinces, 200);
            }else{
            $provinces = Province::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->get();
            return response()->json([
                'data' => $provinces,
            ], 200);
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/province',
    summary: 'Create province',
    tags: ['Province'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    example: 'Jawa Barat'
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
            description: 'Province created successfully'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]  
    public function store(ProvinceCreateRequest $request)
    {
        $data = $request->validated();

        $province = new Province();
        $province->name = $data['name'];
        if ($request->user()->tokenCan("admin-access")) {
            $province->is_accepted = $data['is_accepted'];
        }

        $province->save();

        return response()->json([
            'data' => $province,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/province/{id}',
    summary: 'Update province',
    tags: ['Province'],
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
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    nullable: true,
                    example: 'Jawa Tengah'
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
            description: 'Province updated successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Province not found'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function update(ProvinceUpdateRequest $request, string $id)
    {
        $province = Province::find($id);

        if (!$province) {
            throw new HttpResponseException(response([
                'errors' => 'Province not found',
            ], 404));
        }

        $data = $request->validated();

        $province->name = $data['name'] ?? $province->name;
        $province->is_accepted = $data['is_accepted'] ?? $province->is_accepted;

        $province->save();

        return response()->json([
            'data' => $province,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/province/{id}',
    summary: 'Delete province',
    tags: ['Province'],
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
            response: 200,
            description: 'Province deleted successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Province not found'
        )
    ]
)]
    public function destroy(string $id)
    {
        $province = Province::find($id);

        if (!$province) {
            throw new HttpResponseException(response([
                'errors' => 'Province not found',
            ], 404));
        }

        $province->delete();

        return response()->json([
            'message' => 'Province deleted successfully',
        ], 200);
    }
}

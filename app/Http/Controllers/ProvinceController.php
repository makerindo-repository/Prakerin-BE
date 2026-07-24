<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\Province\ProvinceCreateRequest;
use App\Http\Requests\Province\ProvinceUpdateRequest;
use App\Models\Province;
use App\Models\RegionalDataSyncLog;
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

        $sortBy = $request->query('sort_by', 'external_id');
        $sortDir = strtolower($request->query('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $column = in_array($sortBy, ['code', 'external_id']) ? 'external_id' : 'name';

        Log::info("Province Controller limit = $limit, sort = {$column} {$sortDir}");

        $query = Province::where('is_accepted', $is_accepted)
            ->where('name', "like", "%$search%")
            ->orderBy($column, $sortDir);

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            $provinces = $query->paginate($limit);
            return response()->json($provinces, 200);
        } else {
            if ($isLimit || $request->has('limit')) {
                $provinces = $query->paginate($limit);
                return response()->json($provinces, 200);
            } else {
                $provinces = $query->get();
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
        return response()->json([
            'message' => 'Manual creation of provinces is disabled. Regional data is automatically synchronized from official Kemendagri records. Run `php artisan sync:regional-data`.',
        ], 405);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProvinceUpdateRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Manual editing of provinces is disabled. Regional data is automatically synchronized from official Kemendagri records. Run `php artisan sync:regional-data`.',
        ], 405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return response()->json([
            'message' => 'Manual deletion of provinces is disabled. Regional data is automatically synchronized from official Kemendagri records. Run `php artisan sync:regional-data`.',
        ], 405);
    }

    /**
     * Get regional data sync status.
     */
    public function syncStatus()
    {
        $lastLog = RegionalDataSyncLog::orderBy('created_at', 'desc')->first();
        $totalProvinces = Province::count();

        return response()->json([
            'data' => [
                'total_provinces' => $totalProvinces,
                'last_sync' => $lastLog ? [
                    'source' => $lastLog->sync_source,
                    'status' => $lastLog->status,
                    'completed_at' => $lastLog->completed_at,
                    'provinces_created' => $lastLog->provinces_created,
                    'provinces_updated' => $lastLog->provinces_updated,
                    'cities_created' => $lastLog->cities_created,
                    'cities_updated' => $lastLog->cities_updated,
                ] : null,
            ]
        ], 200);
    }
}

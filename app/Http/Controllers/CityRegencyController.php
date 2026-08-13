<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\CityRegency\CityRegencyCreateRequest;
use App\Http\Requests\CityRegency\CityRegencyUpdateRequest;
use App\Models\CityRegency;
use Arr;
use Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CityRegencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/city-regencies',
        summary: 'Menampilkan daftar city regency',
        tags: ['City Regency']
    )]
    #[OA\Parameter(
        name: 'is_accepted',
        in: 'query',
        required: false,
        description: 'Filter status accepted',
        schema: new OA\Schema(type: 'boolean', default: true)
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        description: 'Pencarian berdasarkan nama',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'province_id',
        in: 'query',
        required: false,
        description: 'Filter berdasarkan Province ID',
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        description: 'Jumlah data per halaman',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil data'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    public function index(Request $request)
    {

        $is_accepted_param = $request->query('is_accepted');
        $search = $request->query('search', '');
        $provinceId = $request->query('province_id', []);
        $limit = $request->query('limit', 10);
        $isLimit = filter_var($request->query('is_limit', false), FILTER_VALIDATE_BOOLEAN);

        $sortBy = $request->query('sort_by', 'external_id');
        $sortDir = strtolower($request->query('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $column = in_array($sortBy, ['code', 'external_id']) ? 'external_id' : 'name';

        Log::info("CityRegency Controller limit = $limit, sort = {$column} {$sortDir}");

        $query = CityRegency::with('province')
            ->when($is_accepted_param !== null, function ($q) use ($is_accepted_param) {
                $q->where('is_accepted', filter_var($is_accepted_param, FILTER_VALIDATE_BOOLEAN));
            })
            ->where('name', "like", "%$search%")
            ->when(!empty($provinceId), function ($q) use ($provinceId) {
                $q->whereIn('province_id', Arr::wrap($provinceId));
            })
            ->orderBy($column, $sortDir);

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access") || $isLimit || $request->has('limit')) {
            $cityRegencies = $query->paginate($limit);
            return response()->json($cityRegencies, 200);
        } else {
            $cityRegencies = $query->get();
            return response()->json([
                'data' => $cityRegencies,
            ], 200);
        }



    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/city-regencies',
        summary: 'Menambahkan city regency',
        tags: ['City Regency']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'province_id'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'province_id', type: 'integer'),
                new OA\Property(property: 'is_accepted', type: 'boolean')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'City Regency berhasil dibuat'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation Error'
    )]
    public function store(CityRegencyCreateRequest $request)
    {
        return response()->json([
            'message' => 'Manual creation of cities/regencies is disabled. Regional data is automatically synchronized from official Kemendagri records. Run `php artisan sync:regional-data`.',
        ], 405);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CityRegencyUpdateRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Manual editing of cities/regencies is disabled. Regional data is automatically synchronized from official Kemendagri records. Run `php artisan sync:regional-data`.',
        ], 405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return response()->json([
            'message' => 'Manual deletion of cities/regencies is disabled. Regional data is automatically synchronized from official Kemendagri records. Run `php artisan sync:regional-data`.',
        ], 405);
    }
}

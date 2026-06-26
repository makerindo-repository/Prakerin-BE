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

        $is_accepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');
        $provinceId = $request->query('province_id', []);
        $limit = $request->query('limit', 10);
        Log::info($provinceId);


        if ($is_accepted === false && !Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            $cityRegencies = CityRegency::where('is_accepted', $is_accepted)
                ->with('province')
                ->where('name', "like", "%$search%")
                ->when(!empty($provinceId), function ($query) use ($provinceId) {
                    $query->whereIn('province_id', Arr::wrap($provinceId));
                })
                ->paginate($limit);


            return response()->json(
                $cityRegencies,
                200
            );
        } else {
            $cityRegencies = CityRegency::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->when(!empty($provinceId), function ($query) use ($provinceId) {
                    $query->whereIn('province_id', Arr::wrap($provinceId));
                })
                ->get();



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
        $data = $request->validated();

        $cityRegency = new CityRegency();
        $cityRegency->name = $data['name'];
        $cityRegency->province_id = $data['province_id'];
        if ($request->user()->tokenCan("admin-access")) {
            $cityRegency->is_accepted = $data['is_accepted'];
        }

        $cityRegency->save();

        return response()->json([
            'data' => $cityRegency,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/city-regencies/{id}',
        summary: 'Update city regency',
        tags: ['City Regency']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'province_id', type: 'integer'),
                new OA\Property(property: 'is_accepted', type: 'boolean')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'City Regency berhasil diupdate'
    )]
    #[OA\Response(
        response: 404,
        description: 'City Regency tidak ditemukan'
    )]
    public function update(CityRegencyUpdateRequest $request, string $id)
    {
        $cityRegency = CityRegency::find($id);

        if (!$cityRegency) {
            throw new HttpResponseException(response([
                'errors' => 'City Regency not found',
            ], 404));
        }

        $data = $request->validated();

        $cityRegency->name = $data['name'] ?? $cityRegency->name;
        $cityRegency->province_id = $data['province_id'] ?? $cityRegency->province_id;
        $cityRegency->is_accepted = $data['is_accepted'] ?? $cityRegency->is_accepted;

        $cityRegency->save();

        return response()->json([
            'data' => $cityRegency,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/city-regencies/{id}',
        summary: 'Hapus city regency',
        tags: ['City Regency']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'City Regency berhasil dihapus'
    )]
    #[OA\Response(
        response: 404,
        description: 'City Regency tidak ditemukan'
    )]
    public function destroy(string $id)
    {
        $cityRegency = CityRegency::find($id);

        if (!$cityRegency) {
            throw new HttpResponseException(response([
                'errors' => 'City Regency not found',
            ], 404));
        }

        $cityRegency->delete();

        return response()->json([
            'message' => 'CityRegency deleted successfully',
        ], 200);
    }
}

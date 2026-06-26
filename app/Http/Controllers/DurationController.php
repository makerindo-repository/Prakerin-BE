<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Duration;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DurationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/durations',
        summary: 'Menampilkan daftar durasi',
        tags: ['Duration']
    )]
    #[OA\Parameter(
        name: 'is_accepted',
        in: 'query',
        required: false,
        description: 'Filter status approval',
        schema: new OA\Schema(type: 'boolean', default: true)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        description: 'Jumlah data per halaman',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'unit',
        in: 'query',
        required: false,
        description: 'Filter satuan durasi',
        schema: new OA\Schema(
            type: 'string',
            enum: ['day', 'month', 'year']
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil data Duration'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    public function index(Request $request)
    {
        $isAccepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $limit = $request->query('limit', 10);
        $unit = $request->query('unit', null);

        if ($isAccepted === false && !Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            $durations = Duration::where('is_accepted', $isAccepted)
                ->when($unit, function ($query) use ($unit) {
                    $query->where('duration_unit', $unit);
                })
                ->orderBy('updated_at', 'desc')
                ->paginate($limit);
            return response()->json(
                $durations,
                200
            );
        } else {
            $durations = Duration::where('is_accepted', $isAccepted)
                ->when($unit, function ($query) use ($unit) {
                    $query->where('duration_unit', $unit);
                })
                ->orderBy('duration_unit', 'asc')
                ->get();
            return response()->json(['data' => $durations], 200);
        }


    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/durations',
        summary: 'Menambahkan Duration',
        tags: ['Duration']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['duration_value', 'duration_unit'],
            properties: [
                new OA\Property(
                    property: 'duration_value',
                    type: 'integer',
                    example: 6
                ),
                new OA\Property(
                    property: 'duration_unit',
                    type: 'string',
                    enum: ['day', 'month', 'year'],
                    example: 'month'
                ),
                new OA\Property(
                    property: 'is_accepted',
                    type: 'boolean',
                    example: true
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Duration berhasil dibuat'
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation Error'
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:day,month,year',
            'is_accepted' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                'errors' => $validator->errors(),
            ], 400));
        }

        $data = $validator->validated();

        $duration = new Duration();
        $duration->duration_value = $data['duration_value'];
        $duration->duration_unit = $data['duration_unit'];
        if ($request->user()->tokenCan("admin-access")) {
            $duration->is_accepted = $data['is_accepted'] ?? false;
        }
        $duration->save();

        return response()->json(['data' => $duration], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/durations/{id}',
        summary: 'Mengubah Duration',
        tags: ['Duration']
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
                new OA\Property(
                    property: 'duration_value',
                    type: 'integer',
                    example: 12
                ),
                new OA\Property(
                    property: 'duration_unit',
                    type: 'string',
                    enum: ['day', 'month', 'year']
                ),
                new OA\Property(
                    property: 'is_accepted',
                    type: 'boolean'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Duration berhasil diupdate'
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation Error'
    )]
    #[OA\Response(
        response: 404,
        description: 'Duration tidak ditemukan'
    )]
    public function update(Request $request, string $id)
    {
        $duration = Duration::find($id);

        if (!$duration) {
            throw new HttpResponseException(response([
                'errors' => 'Duration not found.',
            ], 404));
        }

        $validator = Validator::make($request->all(), [
            'duration_value' => 'sometimes|integer|min:1',
            'duration_unit' => 'sometimes|string|in:day,month,year',
            'is_accepted' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                'errors' => $validator->errors(),
            ], 400));
        }

        $data = $validator->validated();

        $duration->duration_value = $data['duration_value'] ?? $duration->duration_value;
        $duration->duration_unit = $data['duration_unit'] ?? $duration->duration_unit;
        $duration->is_accepted = $data['is_accepted'] ?? $duration->is_accepted;
        $duration->save();

        return response()->json(['data' => $duration], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/durations/{id}',
        summary: 'Menghapus Duration',
        tags: ['Duration']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Duration berhasil dihapus'
    )]
    #[OA\Response(
        response: 404,
        description: 'Duration tidak ditemukan'
    )]
    public function destroy(string $id)
    {
        $duration = Duration::find($id);

        if (!$duration) {
            throw new HttpResponseException(response([
                'errors' => 'Duration not found.',
            ], 404));
        }

        $duration->delete();

        return response()->json([
            'message' => 'Duration deleted successfully.',
        ], 200);
    }
}

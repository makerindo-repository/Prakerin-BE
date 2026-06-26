<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;   
use App\Http\Requests\Field\FieldCreateRequest;
use App\Http\Requests\Field\FieldUpdateRequest;
use App\Models\Field;
use Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/fields',
    summary: 'Menampilkan daftar field',
    tags: ['Field']
)]
#[OA\Parameter(
    name: 'is_accepted',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'boolean', default: true)
)]
#[OA\Parameter(
    name: 'search',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'string')
)]
#[OA\Parameter(
    name: 'limit',
    in: 'query',
    required: false,
    schema: new OA\Schema(type: 'integer', default: 10)
)]
#[OA\Response(
    response: 200,
    description: 'Berhasil mengambil daftar field'
)]
#[OA\Response(
    response: 403,
    description: 'Forbidden'
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
            $fields = Field::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->paginate($limit);

            return response()->json(
                $fields,
                200
            );
        } else {
            $fields = Field::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->get();

            return response()->json([
                'data' => $fields,
            ], 200);
        }


    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/fields',
    summary: 'Menambahkan field',
    tags: ['Field']
)]
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ['name'],
        properties: [
            new OA\Property(
                property: 'name',
                type: 'string'
            ),
            new OA\Property(
                property: 'is_accepted',
                type: 'boolean'
            )
        ]
    )
)]
#[OA\Response(
    response: 201,
    description: 'Field berhasil dibuat'
)]
#[OA\Response(
    response: 422,
    description: 'Validation Error'
)]
    public function store(FieldCreateRequest $request)
    {
        $data = $request->validated();

        $field = new Field();
        $field->name = $data['name'];
        if ($request->user()->tokenCan("admin-access")) {
            $field->is_accepted = $data['is_accepted'];
        }

        $field->save();

        return response()->json([
            'data' => $field,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/fields/{id}',
    summary: 'Mengubah field',
    tags: ['Field']
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
                property: 'name',
                type: 'string'
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
    description: 'Field berhasil diupdate'
)]
#[OA\Response(
    response: 404,
    description: 'Field tidak ditemukan'
)]
    public function update(FieldUpdateRequest $request, string $id)
    {
        $field = Field::find($id);

        if (!$field) {
            throw new HttpResponseException(response([
                'errors' => 'Field not found',
            ], 404));
        }

        $data = $request->validated();

        $field->name = $data['name'] ?? $field->name;
        $field->is_accepted = $data['is_accepted'] ?? $field->is_accepted;

        $field->save();

        return response()->json([
            'data' => $field,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/fields/{id}',
    summary: 'Menghapus field',
    tags: ['Field']
)]
#[OA\Parameter(
    name: 'id',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer')
)]
#[OA\Response(
    response: 200,
    description: 'Field berhasil dihapus'
)]
#[OA\Response(
    response: 404,
    description: 'Field tidak ditemukan'
)]
    public function destroy(string $id)
    {
        $field = Field::find($id);

        if (!$field) {
            throw new HttpResponseException(response([
                'errors' => 'Field not found',
            ], 404));
        }

        $field->delete();

        return response()->json([
            'message' => 'Field deleted successfully',
        ], 200);
    }
}

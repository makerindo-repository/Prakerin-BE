<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\CurriculumVitae\CurriculumVitaeCreateRequest;
use App\Models\CurriculumVitae;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CurriculumVitaeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/curriculum-vitaes',
        summary: 'Menampilkan daftar Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil daftar Curriculum Vitae'
    )]
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search', '');
        $curriculumVitaes = CurriculumVitae::where('name', 'like', "%$search%")
            ->where('student_id', $request->user()->student->id)
            ->paginate($limit);

        return response()->json($curriculumVitaes);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/curriculum-vitaes',
        summary: 'Menambahkan Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['name', 'file'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Curriculum Vitae berhasil dibuat'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation Error'
    )]
    public function store(CurriculumVitaeCreateRequest $request)
    {
        $data = $request->validated();

        $curriculumVitae = new CurriculumVitae();
        $curriculumVitae->name = $data['name'];

        $filename = now()->format('Ymd_His') . '.' . $request->file('file')->getClientOriginalExtension();
        $curriculumVitae->file = $filename;
        $request->file('file')->storeAs('curriculum-vitaes', $filename);
        $curriculumVitae->student_id = $request->user()->student->id;
        $curriculumVitae->save();

        return response()->json([
            'data' => $curriculumVitae
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/curriculum-vitaes/{id}',
        summary: 'Menampilkan detail Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil detail Curriculum Vitae'
    )]
    #[OA\Response(
        response: 401,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Curriculum Vitae tidak ditemukan'
    )]
    public function show(string $id)
    {
        $curriculumVitae = CurriculumVitae::find($id);

        if (!$curriculumVitae) {
             throw new HttpResponseException(response([
                "errors" => "Curriculum Vitae not found."
            ], 404));
        }

        if ($curriculumVitae->student_id !== request()->user()->student->id) {
             throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 401));
        }

        return response()->json(
            ['data' => $curriculumVitae],
            200
        );
    }


    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/curriculum-vitaes/{id}',
        summary: 'Mengubah Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Curriculum Vitae berhasil diubah'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Curriculum Vitae tidak ditemukan'
    )]
    public function update(Request $request, string $id)
    {
        $curriculumVitae = CurriculumVitae::find($id);

        if (!$curriculumVitae) {
            throw new HttpResponseException(response([
                "errors" => "Curriculum Vitae not found."
            ], 404));
        }

        if ($curriculumVitae->student_id !== $request->user()->student->id) {
            throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 403));
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'file' => 'sometimes|required|file|mimes:pdf|max:2048',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                "errors" => $validator->errors()
            ], 422));
        }

        $data = $validator->validated();


        $curriculumVitae->name = $data['name'] ?? $curriculumVitae->name;

        if (isset($data['file'])) {
            // Hapus file lama kalau ada
            if (Storage::exists("/curriculum-vitaes/$curriculumVitae->file")) {
                Storage::delete("/curriculum-vitaes/$curriculumVitae->file");
            }

            // Simpan file baru
            $filename = now()->format('Ymd_His') . '.' . $request->file('file')->getClientOriginalExtension();
            $curriculumVitae->file = $filename;
            $request->file('file')->storeAs('curriculum-vitaes', $filename);
        }

        $curriculumVitae->save();

        return response()->json([
            'data' => $curriculumVitae
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/curriculum-vitaes/{id}',
        summary: 'Menghapus Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Curriculum Vitae berhasil dihapus'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Curriculum Vitae tidak ditemukan'
    )]
    public function destroy(string $id)
    {
        $curriculumVitae = CurriculumVitae::find($id);

        if (!$curriculumVitae) {
            throw new HttpResponseException(response([
                "errors" => "Curriculum Vitae not found."
            ], 404));
        }

        if ($curriculumVitae->student_id !== request()->user()->student->id) {
            throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 403));
        }

        // Hapus file lama kalau ada
        if (Storage::exists("/curriculum-vitaes/$curriculumVitae->file")) {
            Storage::delete("/curriculum-vitaes/$curriculumVitae->file");
        }

        // Hapus record dari database
        $curriculumVitae->delete();

        return response()->json([
            'message' => 'Curriculum Vitae and file deleted successfully.'
        ], 200);

    }


    #[OA\Get(
        path: '/curriculum-vitaes/{id}/preview',
        summary: 'Preview file Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Preview PDF berhasil'
    )]
    #[OA\Response(
        response: 404,
        description: 'File atau Curriculum Vitae tidak ditemukan'
    )]
    public function preview(Request $request, string $id)
    {
        $cv = CurriculumVitae::find($id);

        if (!$cv) {
            return response()->json([
                'errors' => 'Curriculum Vitae not found.'
            ], 404);
        }

        // if ($cv->student_id !== $request->user()->student->id) {
        //     return response()->json([
        //         'errors' => 'Forbidden.'
        //     ], 403);
        // }

        if (!Storage::exists("/curriculum-vitaes/$cv->file")) {
            return response()->json([
                'errors' => 'File not found.'
            ], 404);
        }
        $path = Storage::path("/curriculum-vitaes/$cv->file");

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
        ]);
    }


    #[OA\Get(
        path: '/curriculum-vitaes/{id}/download',
        summary: 'Download file Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'File berhasil didownload'
    )]
    #[OA\Response(
        response: 404,
        description: 'File atau Curriculum Vitae tidak ditemukan'
    )]
    public function download(Request $request, string $id)
    {
        $cv = CurriculumVitae::find($id);

        if (!$cv) {
            return response()->json([
                'errors' => 'Curriculum Vitae not found.'
            ], 404);
        }

        // if ($cv->student_id !== $request->user()->student->id) {
        //     return response()->json([
        //         'errors' => 'Forbidden.'
        //     ], 403);
        // }

        if (!Storage::exists("/curriculum-vitaes/$cv->file")) {
            return response()->json([
                'errors' => 'File not found.'
            ], 404);
        }
        $path = Storage::path("/curriculum-vitaes/$cv->file");


        return response()->download($path, 'cv_' . now()->format('Ymd_His') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}

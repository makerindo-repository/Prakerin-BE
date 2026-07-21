<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;



class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/applications',
        summary: 'List Application',
        tags: ['Application']
    )]
    #[OA\Response(
        response: 200,
        description: 'Success'
    )]
    public function index()
    {
        return response()->json([
            'data' => Application::all()
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */

    #[OA\Post(
        path: '/applications',
        summary: 'Menambahkan application',
        tags: ['Application']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['internship_id', 'cv'],
                properties: [
                    new OA\Property(
                        property: 'internship_id',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'cv',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Application berhasil dibuat'
    )]
    public function store(Request $request)
    {
        if ($request->user()->tokenCant('application:create')) {
            return response()->json([
                'error' => "you can't access this route"
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'internship_id' =>  'required|numeric',
            'cv' => 'required|mimes:pdf,docx,doc,txt,rtf,tex|max:2082'
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()], 422);
        }

        $data = $request->only('internship_id');
        // return dd($request->user()->student);
        $data['student_id'] = $request->user()->student?->id;
        if (Application::where('internship_id', $data['internship_id'])->where('student_id', $data['student_id'])->exists()) {
            return response()->json([
                'error' => 'Kamu tidak bisa melamar dua kali'
            ], 403);
        }
        if ($request->file('cv')) {
            $data['cv'] = $request->file('cv')->getClientOriginalName();
            $request->file('cv')->storeAs('cv', $data['cv']);
        }
        Application::create($data);
        return response()->json(["data" => $data], 200);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/applications/{id}',
        summary: 'Menampilkan detail application',
        tags: ['Application']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID Application',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Success'
    )]
    #[OA\Response(
        response: 404,
        description: 'Application tidak ditemukan'
    )]
    public function show(string $id)
    {
        if (!$data = Application::find($id)) {
            return response()->json([
                'error' => 'Application not found!'
            ], 404);
        }

        return response()->json([
            'data' => $data
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/applications/{id}',
        summary: 'Update application',
        tags: ['Application']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID Application',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'internship_id',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'cv',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Application berhasil diupdate'
    )]
    #[OA\Response(
        response: 404,
        description: 'Application tidak ditemukan'
    )]
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'internship_id' =>  'required|numeric',
            'cv' => 'required|mimes:pdf,docx,doc,txt,rtf,tex|max:2082'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/applications/{id}',
        summary: 'Hapus application',
        tags: ['Application']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID Application',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Application berhasil dihapus'
    )]
    #[OA\Response(
        response: 404,
        description: 'Application tidak ditemukan'
    )]
    public function destroy(string $id)
    {
        //tes
    }
}

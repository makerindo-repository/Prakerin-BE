<?php

namespace App\Http\Controllers;

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

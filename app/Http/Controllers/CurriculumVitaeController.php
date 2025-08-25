<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurriculumVitae\CurriculumVitaeCreateRequest;
use App\Http\Requests\CurriculumVitae\CurriculumVitaeUpdateRequest;
use App\Models\CurriculumVitae;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

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
            return throw new HttpResponseException(response([
                "errors" => "Curriculum Vitae not found."
            ], 404));
        }

        if ($curriculumVitae->student_id !== request()->user()->student->id) {
            return throw new HttpResponseException(response([
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
    public function update(Request $request, CurriculumVitaeUpdateRequest $curriculumVitaeUpdateRequest, string $id)
    {

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $curriculumVitae = CurriculumVitae::find($id);

        if (!$curriculumVitae) {
            return throw new HttpResponseException(response([
                "errors" => "Curriculum Vitae not found."
            ], 404));
        }

        if ($curriculumVitae->student_id !== request()->user()->student->id) {
            return throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 401));
        }

        $curriculumVitae->delete();

        return response()->json([
            'message' => 'Curriculum Vitae deleted successfully.'
        ], 200);
    }

    public function download(Request $request, string $id)
    {

    }
}

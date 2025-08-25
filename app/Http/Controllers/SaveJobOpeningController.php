<?php

namespace App\Http\Controllers;

use App\Models\SaveJobOpening;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaveJobOpeningController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $limit = request()->query('limit', 10);
        $saveJobOpenings = SaveJobOpening::with('jobOpening.company.user')
            ->where('student_id', auth()->user()->student->id)
            ->paginate($limit);

        return response()->json($saveJobOpenings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_opening_id' => 'required|exists:job_openings,id',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $existing = SaveJobOpening::where('student_id', auth()->user()->student->id)
            ->where('job_opening_id', $request->job_opening_id)
            ->first();

        if ($existing) {
            throw new HttpResponseException(response()->json([
                'message' => 'Job opening already saved.'
            ], 400));
        }

        $saveJobOpening = SaveJobOpening::create([
            'student_id' => auth()->user()->student->id,
            'job_opening_id' => $request->job_opening_id,
        ]);

        return response()->json([
            'data' => $saveJobOpening
        ], 201);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $saveJobOpening = SaveJobOpening::find($id);

        if (!$saveJobOpening) {
            throw new HttpResponseException(response()->json([
                'message' => 'Save Job Opening not found.'
            ], 404));
        }

        if ($saveJobOpening->student_id !== auth()->user()->student->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Forbidden.'
            ], 403));
        }

        $saveJobOpening->delete();
    }
}

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

        // ubah struktur biar user sejajar dengan company
        $saveJobOpenings->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'student_id' => $item->student_id,
                'job_opening_id' => $item->job_opening_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,

                'job_opening' => [
                    'id' => $item->jobOpening->id,
                    'title' => $item->jobOpening->title,
                    'description' => $item->jobOpening->description,
                    'grade' => $item->jobOpening->grade,
                    'type' => $item->jobOpening->type,
                    'is_paid' => $item->jobOpening->is_paid,
                    'is_available' => $item->jobOpening->is_available,
                ],

                'company' => [
                    'id' => $item->jobOpening->company->id,
                    'name' => $item->jobOpening->company->name,
                    'address' => $item->jobOpening->company->address,
                    'phone_number' => $item->jobOpening->company->phone_number,
                ],

                'user' => [
                    'id' => $item->jobOpening->company->user->id,
                    'username' => $item->jobOpening->company->user->username,
                    'email' => $item->jobOpening->company->user->email,
                    'role' => $item->jobOpening->company->user->role,
                ]
            ];
        });

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

        return response()->json([
            'message' => 'Save Job Opening deleted successfully.'
        ], 200);
    }
}

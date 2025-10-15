<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Internship;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InternshipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);

        $internships = Internship::paginate($limit);

        return response()->json($internships);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {



    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $internship = Internship::find($id);

        if (!$internship) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship not found.'],
                404
            ));
        }

        if ($internship->internshipApplication->jobOpening->company_id !== auth()->user()->company->id) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Forbidden.'],
                403
            ));
        }
        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_completed' => 'sometimes|required|boolean',
            'role_id' => 'sometimes|required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                ['errors' => $validator->errors()],
                400
            ));
        }

        $data = $validator->validated();



        if (isset($data['is_completed']) && $data['is_completed'] === true) {
            $certificate = new Certificate();
            $certificate->internship_id = $internship->id;
            $certificate->save();
            auth()->user->rated()->attach($internship->students->user->id);
        }


        $internship->update($data);
        $internship->save();

        $internship->makeHidden('internshipApplication');

        return response()->json([
            'data' => $internship,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $internship = Internship::find($id);

        if (!$internship) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Internship not found.'],
                404
            ));
        }

        if ($internship->internshipApplication->jobOpening->company_id !== auth()->user()->company->id) {
            throw new HttpResponseException(response()->json(
                ['errors' => 'Forbidden.'],
                403
            ));
        }

        $internship->delete();

        return response()->json([
            'data' => 'Internship deleted successfully.',
        ], 200);
    }

    public function count(Request $request)
    {
        $count = Internship::where('is_completed', false)
            ->whereHas('internshipApplication.jobOpening', function ($query) use ($request) {
                $query->where('company_id', $request->user()->company->id);
            })
            ->count();


        return response()->json([
            'data' => $count
        ]);
    }
}

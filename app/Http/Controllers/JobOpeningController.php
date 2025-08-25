<?php

namespace App\Http\Controllers;

use App\Models\JobOpening;
use App\Models\SaveJobOpening;
use Illuminate\Http\Request;

class JobOpeningController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $limit = request()->query('limit', 10);
        $search = request()->query('search', '');
        $province_id = request()->query('province_id', '');
        $city_regency_id = request()->query('city_regency_id', '');
        $grade = request()->query('grade', '');
        $field_id = request()->query('field_id', '');
        $duration_id = request()->query('duration_id', '');
        $jobOpenings = JobOpening::with('company.user', 'saveJobOpening')->paginate($limit);

        return response()->json($jobOpenings);
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

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
        $jobOpenings = JobOpening::with('company.user')
            ->whereHas('company', function ($query) use ($province_id, $city_regency_id) {
                if ($province_id && !$city_regency_id) {
                    $query->whereHas('cityRegency', function ($query) use ($province_id) {
                        $query->where('province_id', $province_id);
                    });
                }
                if ($city_regency_id) {
                    $query->where('city_regency_id', $city_regency_id);
                }
            })
            ->where('title', 'like', "%{$search}%")

            ->when($grade === 'smk' || $grade === 'mahasiswa' || $grade === 'all', function ($query, $grade) {
                return $query->where('grade', $grade);
            })

            ->when($field_id, function ($query, $field_id) {
                return $query->where('field_id', $field_id);
            })

            ->when($duration_id, function ($query, $duration_id) {
                return $query->where('duration_id', $duration_id);
            })

            ->paginate($limit);


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

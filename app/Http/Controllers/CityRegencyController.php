<?php

namespace App\Http\Controllers;

use App\Http\Requests\CityRegency\CityRegencyCreateRequest;
use App\Http\Requests\CityRegency\CityRegencyUpdateRequest;
use App\Models\CityRegency;
use App\Models\Province;
use Arr;
use Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class CityRegencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $is_accepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');
        $provinceId = $request->query('province_id', []);
        $limit = $request->query('limit', 10);

        if ($is_accepted === false && !Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {

            $cityRegencies = CityRegency::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->when(!empty($provinceId), function ($query) use ($provinceId) {
                    $query->whereIn('province_id', Arr::wrap($provinceId));
                })
                ->paginate($limit);


            return response()->json(
                $cityRegencies,
                200
            );
        } else {

            $cityRegencies = CityRegency::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->when(!empty($provinceId), function ($query) use ($provinceId) {
                    $query->whereIn('province_id', Arr::wrap($provinceId));
                })
                ->get();



            return response()->json([
                'data' => $cityRegencies,
            ], 200);
        }



    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CityRegencyCreateRequest $request)
    {
        $data = $request->validated();

        $cityRegency = new CityRegency();
        $cityRegency->name = $data['name'];
        $cityRegency->province_id = $data['province_id'];
        if ($request->user()->tokenCan("admin-access")) {
            $cityRegency->is_accepted = $data['is_accepted'];
        }

        $cityRegency->save();

        return response()->json([
            'data' => $cityRegency,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CityRegencyUpdateRequest $request, string $id)
    {
        $cityRegency = CityRegency::find($id);

        if (!$cityRegency) {
            throw new HttpResponseException(response([
                'errors' => 'City Regency not found',
            ], 404));
        }

        $data = $request->validated();

        $cityRegency->name = $data['name'] ?? $cityRegency->name;
        $cityRegency->province_id = $data['province_id'] ?? $cityRegency->province_id;
        $cityRegency->is_accepted = $data['is_accepted'] ?? $cityRegency->is_accepted;

        $cityRegency->save();

        return response()->json([
            'data' => $cityRegency,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $cityRegency = CityRegency::find($id);

        if (!$cityRegency) {
            throw new HttpResponseException(response([
                'errors' => 'City Regency not found',
            ], 404));
        }

        $cityRegency->delete();

        return response()->json([
            'message' => 'CityRegency deleted successfully',
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Province\ProvinceCreateRequest;
use App\Http\Requests\Province\ProvinceUpdateRequest;
use App\Models\Province;
use Auth;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $is_accepted = true;
        $search = $request->query('search', '');

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            $is_accepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        }

        $provinces = Province::where('is_accepted', $is_accepted)->where('name', "like", "%$search%")->get();

        return response()->json([
            'data' => $provinces,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProvinceCreateRequest $request)
    {
        $data = $request->validated();

        $province = new Province();
        $province->name = $data['name'];
        if ($request->user()->tokenCan("admin-access")) {
            $province->is_accepted = $data['is_accepted'];
        }

        $province->save();

        return response()->json([
            'data' => $province,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProvinceUpdateRequest $request, string $id)
    {
        $province = Province::find($id);

        if (!$province) {
            return response()->json([
                'errors' => 'Province not found',
            ], 404);
        }

        $data = $request->validated();

        $province->name = $data['name'] ?? $province->name;
        $province->is_accepted = $data['is_accepted'] ?? $province->is_accepted;

        $province->save();

        return response()->json([
            'data' => $province,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $province = Province::find($id);

        if (!$province) {
            return response()->json([
                'errors' => 'Province not found',
            ], 404);
        }

        $province->delete();

        return response()->json([
            'message' => 'Province deleted successfully',
        ], 200);
    }
}

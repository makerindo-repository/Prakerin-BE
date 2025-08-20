<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sector\SectorCreateRequest;
use App\Http\Requests\Sector\SectorUpdateRequest;
use App\Models\Sector;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SectorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $is_accepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');

        if ($is_accepted === false && !Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        $sectors = Sector::where('is_accepted', $is_accepted)->where('name', "like", "%$search%")->get();

        return response()->json([
            'data' => $sectors,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SectorCreateRequest $request)
    {
        $data = $request->validated();

        $sector = new Sector();
        $sector->name = $data['name'];
        if ($request->user()->tokenCan("admin-access")) {
            $sector->is_accepted = $data['is_accepted'];
        }

        $sector->save();

        return response()->json([
            'data' => $sector,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SectorUpdateRequest $request, string $id)
    {
        $sector = Sector::find($id);

        if (!$sector) {
            throw new HttpResponseException(response([
                'errors' => 'Sector not found',
            ], 404));
        }

        $data = $request->validated();

        $sector->name = $data['name'] ?? $sector->name;
        $sector->is_accepted = $data['is_accepted'] ?? $sector->is_accepted;

        $sector->save();

        return response()->json([
            'data' => $sector,
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sector = Sector::find($id);

        if (!$sector) {
            throw new HttpResponseException(response([
                'errors' => 'Sector not found',
            ], 404));
        }

        $sector->delete();

        return response()->json([
            'message' => 'Sector deleted successfully',
        ], 200);
    }
}

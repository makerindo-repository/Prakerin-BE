<?php

namespace App\Http\Controllers;

use App\Models\Duration;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DurationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $isAccepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);

        if ($isAccepted === false && !Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        $durations = Duration::where('is_accepted', $isAccepted)->get();

        return response()->json(['data' => $durations], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:day,month,year',
            'is_accepted' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                'errors' => $validator->errors(),
            ], 400));
        }

        $data = $validator->validated();

        $duration = new Duration();
        $duration->duration_value = $data['duration_value'];
        $duration->duration_unit = $data['duration_unit'];
        if ($request->user()->tokenCan("admin-access")) {
            $duration->is_accepted = $data['is_accepted'] ?? false;
        }
        $duration->save();

        return response()->json(['data' => $duration], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $duration = Duration::find($id);

        if (!$duration) {
            throw new HttpResponseException(response([
                'errors' => 'Duration not found.',
            ], 404));
        }

        $validator = Validator::make($request->all(), [
            'duration_value' => 'sometimes|integer|min:1',
            'duration_unit' => 'sometimes|string|in:days,weeks,months',
            'is_accepted' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                'errors' => $validator->errors(),
            ], 400));
        }

        $data = $validator->validated();

        $duration->duration_value = $data['duration_value'] ?? $duration->duration_value;
        $duration->duration_unit = $data['duration_unit'] ?? $duration->duration_unit;
        $duration->is_accepted = $data['is_accepted'] ?? $duration->is_accepted;
        $duration->save();

        return response()->json(['data' => $duration], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $duration = Duration::find($id);

        if (!$duration) {
            throw new HttpResponseException(response([
                'errors' => 'Duration not found.',
            ], 404));
        }

        $duration->delete();

        return response()->json([
            'message' => 'Duration deleted successfully.',
        ], 200);
    }
}

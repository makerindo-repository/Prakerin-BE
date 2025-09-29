<?php

namespace App\Http\Controllers;

use App\Models\Major;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MajorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $is_accepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');
        $limit = $request->query('limit', 10);
        $level = $request->query('level', null);


        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {
            $majors = Major::where('is_accepted', $is_accepted)
                ->when($level, function ($query) use ($level) {
                    $query->where('level', $level);
                })
                ->where('name', "like", "%$search%")
                ->orderBy('updated_at', 'desc')
                ->paginate($limit);
            return response()->json(
                $majors,
                200
            );
        } else {
            $majors = Major::where('is_accepted', $is_accepted)
                ->where('name', "like", "%$search%")
                ->when($level, function ($query) use ($level) {
                    $query->where('level', $level);
                })
                ->orderBy('name', 'desc')
                ->get();
            return response()->json([
                'data' => $majors,
            ], 200);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'level' => 'required|in:smk,college',
        ]);

        if ($validated->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validated->errors(),
            ], 400));
        }

        $data = $validated->validated();

        $major = new Major();
        $major->name = $data['name'];
        $major->level = $data['level'];
        $major->is_accepted = true;
        $major->save();

        return response()->json([
            'data' => true,
        ], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $major = Major::find($id);

        if (!$major) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Major not found',
            ], 404));
        }

        $validated = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'level' => 'sometimes|required|in:smk,college',
            'is_accepted' => 'sometimes|required|boolean',
        ]);

        if ($validated->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validated->errors(),
            ], 400));
        }

        $data = $validated->validated();
        foreach (['name', 'level', 'is_accepted'] as $field) {
            if (isset($data[$field])) {
                $major->$field = $data[$field];
            }
        }

        $major->save();

        return response()->json([
            'data' => true,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $major = Major::find($id);

        if (!$major) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Major not found',
            ], 404));
        }

        $major->delete();

        return response()->json([
            'data' => true,
        ], 200);
    }
}

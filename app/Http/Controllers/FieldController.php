<?php

namespace App\Http\Controllers;

use App\Http\Requests\Field\FieldCreateRequest;
use App\Http\Requests\Field\FieldUpdateRequest;
use App\Models\Field;
use Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class FieldController extends Controller
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

        $fields = Field::where('is_accepted', $is_accepted)->where('name', "like", "%$search%")->get();

        return response()->json([
            'data' => $fields,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FieldCreateRequest $request)
    {
        $data = $request->validated();

        $field = new Field();
        $field->name = $data['name'];
        if ($request->user()->tokenCan("admin-access")) {
            $field->is_accepted = $data['is_accepted'];
        }

        $field->save();

        return response()->json([
            'data' => $field,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FieldUpdateRequest $request, string $id)
    {
        $field = Field::find($id);

        if (!$field) {
            throw new HttpResponseException(response([
                'errors' => 'Field not found',
            ], 404));
        }

        $data = $request->validated();

        $field->name = $data['name'] ?? $field->name;
        $field->is_accepted = $data['is_accepted'] ?? $field->is_accepted;

        $field->save();

        return response()->json([
            'data' => $field,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $field = Field::find($id);

        if (!$field) {
            throw new HttpResponseException(response([
                'errors' => 'Field not found',
            ], 404));
        }

        $field->delete();

        return response()->json([
            'message' => 'Field deleted successfully',
        ], 200);
    }
}

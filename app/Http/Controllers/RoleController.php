<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $isAccepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');

        if ($isAccepted === false && !$request->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        $roles = Role::where('is_accepted', $isAccepted)->where('name', "like", "%$search%")->get();

        return response()->json([
            'data' => $roles,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_accepted' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $data = $validator->validated();

        $role = new Role();
        $role->name = $data['name'];
        if ($request->user()->tokenCan("admin-access")) {
            $role->is_accepted = $data['is_accepted'] ?? false;
        }
        $role->save();

        return response()->json([
            'data' => $role
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::find($id);
        if (!$role) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Role not found'
            ], 404));
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'is_accepted' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $data = $validator->validated();

        $role->name = $data['name'] ?? $role->name;
        $role->is_accepted = $data['is_accepted'] ?? $role->is_accepted;
        $role->save();

        return response()->json([
            'data' => $role
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::find($id);
        if (!$role) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Role not found'
            ], 404));
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully'
        ], 200);
    }
}

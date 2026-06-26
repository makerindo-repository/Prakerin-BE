<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Role;
use Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/role',
    summary: 'Get role list',
    tags: ['Role'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Search role name',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'is_accepted',
            in: 'query',
            required: false,
            description: 'Filter accepted role',
            schema: new OA\Schema(type: 'boolean', default: true)
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Pagination limit',
            schema: new OA\Schema(type: 'integer', default: 10)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Role list retrieved successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        )
    ]
)]
    public function index(Request $request)
    {
        $isAccepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');
        $limit = $request->query('limit', 10);

        if ($isAccepted === false && !$request->user()?->tokenCan("admin-access")) {
            throw new HttpResponseException(response([
                'errors' => 'Forbidden.',
            ], 403));
        }

        if (Auth::guard('sanctum')->user()?->tokenCan("admin-access")) {

            $roles = Role::where('is_accepted', $isAccepted)
                ->where('name', "like", "%$search%")
                ->paginate($limit);

            return response()->json(
                $roles,
                200
            );

        } else {

            $roles = Role::where('is_accepted', $isAccepted)
                ->where('name', "like", "%$search%")
                ->get();

            return response()->json([
                'data' => $roles,
            ], 200);
        }

    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/role',
    summary: 'Create role',
    tags: ['Role'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    example: 'Software Engineer'
                ),
                new OA\Property(
                    property: 'is_accepted',
                    type: 'boolean',
                    nullable: true,
                    example: true
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Role created successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        )
    ]
)]
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
    #[OA\Put(
    path: '/role/{id}',
    summary: 'Update role',
    tags: ['Role'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Role ID',
            schema: new OA\Schema(type: 'string')
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    nullable: true,
                    example: 'Backend Developer'
                ),
                new OA\Property(
                    property: 'is_accepted',
                    type: 'boolean',
                    nullable: true,
                    example: true
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Role updated successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        ),
        new OA\Response(
            response: 404,
            description: 'Role not found'
        )
    ]
)]
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
    #[OA\Delete(
    path: '/role/{id}',
    summary: 'Delete role',
    tags: ['Role'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Role ID',
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Role deleted successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Role not found'
        )
    ]
)]
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

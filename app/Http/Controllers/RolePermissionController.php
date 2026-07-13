<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    /**
     * Get all roles along with their permissions.
     */
    public function index()
    {
        $roles = Role::where('guard_name', 'sanctum')->with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ];
        });

        return response()->json([
            'data' => $roles,
        ], 200);
    }

    /**
     * Get all available permissions.
     */
    public function getAllPermissions()
    {
        $permissions = Permission::where('guard_name', 'sanctum')->pluck('name');

        return response()->json([
            'data' => $permissions,
        ], 200);
    }

    /**
     * Sync permissions to a specific role.
     */
    public function updateRolePermissions(Request $request, string $roleName)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:auth_permissions,name',
        ]);

        $role = Role::where('name', $roleName)->where('guard_name', 'sanctum')->first();

        if (!$role) {
            return response()->json([
                'message' => "Role '{$roleName}' not found.",
            ], 404);
        }

        // Sync permissions
        $role->syncPermissions($request->input('permissions'));

        // Clear cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => "Permissions for role '{$roleName}' updated successfully.",
            'data' => [
                'role' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
            ]
        ], 200);
    }
}

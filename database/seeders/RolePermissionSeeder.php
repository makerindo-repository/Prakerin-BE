<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define permissions list grouped by target resource
        $permissions = [
            // Job vacancies / lowongan
            'view-lowongan',
            'create-lowongan',
            'edit-lowongan',
            'delete-lowongan',
            'apply-lowongan',
            'view-applicants',

            // CV management
            'manage-cv',

            // Task list
            'view-tasklist',
            'submit-task',
            'manage-tasklist',

            // Feedback / Ulasan
            'view-feedback',
            'create-feedback',

            // Certificate
            'view-sertifikat',
            'create-sertifikat',

            // MOU / Kerja sama
            'manage-mou',

            // School & Placement
            'view-students',
            'manage-placement',
            'view-companies',

            // Admin Master Data & User Management
            'manage-master-data',
            'manage-users',
            'edit-page-content',
        ];

        // Create permissions
        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // 2. Define roles and map permissions to them
        $rolePermissions = [
            'siswa' => [
                'view-lowongan',
                'apply-lowongan',
                'manage-cv',
                'view-tasklist',
                'submit-task',
                'view-feedback',
                'view-sertifikat',
            ],
            'mahasiswa' => [
                'view-lowongan',
                'apply-lowongan',
                'manage-cv',
                'view-tasklist',
                'submit-task',
                'view-feedback',
                'view-sertifikat',
            ],
            'company_owner' => [
                'view-lowongan',
                'create-lowongan',
                'edit-lowongan',
                'delete-lowongan',
                'view-applicants',
                'manage-tasklist',
                'view-feedback',
                'create-feedback',
                'view-sertifikat',
                'create-sertifikat',
                'manage-mou',
            ],
            'company_admin' => [
                'view-lowongan',
                'create-lowongan',
                'edit-lowongan',
                'delete-lowongan',
                'view-applicants',
                'manage-tasklist',
                'view-feedback',
                'create-feedback',
                'view-sertifikat',
                'create-sertifikat',
                'manage-mou',
            ],
            'school_admin' => [
                'view-students',
                'manage-placement',
                'view-companies',
                'manage-mou',
                'view-feedback',
            ],
            'university_admin' => [
                'view-students',
                'manage-placement',
                'view-companies',
                'manage-mou',
                'view-feedback',
            ],
            'super_admin' => $permissions, // Super admin gets all permissions
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($perms);
        }

        // 3. Sync existing users to Spatie roles
        $users = User::all();
        foreach ($users as $user) {
            if ($user->role && Role::where('name', $user->role)->exists()) {
                $user->assignRole($user->role);
            }
        }
    }
}

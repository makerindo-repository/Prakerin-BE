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
     *
     * Permission format: action_feature (e.g. view_kelas, create_kelas)
     * Matches 2_PERMISSION_PLAN.md
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define all permissions grouped by feature
        $permissions = [
            // Dashboard
            'view_dashboard',

            // Kelas Pra-Magang
            'view_kelas', 'create_kelas', 'edit_kelas', 'delete_kelas',

            // Pembimbing (Mentor)
            'view_pembimbing', 'create_pembimbing', 'edit_pembimbing', 'delete_pembimbing',

            // Manajemen User
            'view_manajemen_user', 'create_manajemen_user', 'edit_manajemen_user', 'delete_manajemen_user',

            // Isi Halaman (Page Content)
            'view_isi_halaman', 'create_isi_halaman', 'edit_isi_halaman', 'delete_isi_halaman',

            // Panduan (Guidelines)
            'view_panduan', 'create_panduan', 'edit_panduan', 'delete_panduan',

            // Feedback Pengguna
            'view_feedback', 'approve_feedback',

            // Laporan (Reports)
            'view_laporan', 'create_laporan', 'edit_laporan', 'delete_laporan',

            // Log Aktivitas
            'view_log_aktivitas',

            // Pengaturan (Settings)
            'view_pengaturan', 'edit_pengaturan',

            // Profil
            'view_profil', 'edit_profil',

            // Special
            'manage_roles', 'manage_permissions',

            // DEV features
            'view_ai_analytics',
            'view_data_provinsi', 'create_data_provinsi', 'edit_data_provinsi', 'delete_data_provinsi',
            'view_data_kota', 'create_data_kota', 'edit_data_kota', 'delete_data_kota',
            'view_data_sektor_industri', 'create_data_sektor_industri', 'edit_data_sektor_industri', 'delete_data_sektor_industri',
            'view_data_durasi_magang', 'create_data_durasi_magang', 'edit_data_durasi_magang', 'delete_data_durasi_magang',
            'view_data_jurusan_siswa', 'create_data_jurusan_siswa', 'edit_data_jurusan_siswa', 'delete_data_jurusan_siswa',
            'view_data_bidang_magang', 'create_data_bidang_magang', 'edit_data_bidang_magang', 'delete_data_bidang_magang',
            'view_data_sekolah', 'create_data_sekolah', 'edit_data_sekolah', 'delete_data_sekolah',
            'view_data_perguruan_tinggi', 'create_data_perguruan_tinggi', 'edit_data_perguruan_tinggi', 'delete_data_perguruan_tinggi',
            'view_data_industri', 'create_data_industri', 'edit_data_industri', 'delete_data_industri',
        ];

        // Create all permissions
        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // 2. Define roles and their default permission mappings
        $rolePermissions = [
            'siswa' => [
                'view_dashboard', 'view_kelas', 'view_pembimbing',
                'view_panduan', 'view_feedback', 'view_profil', 'edit_profil',
            ],
            'mahasiswa' => [
                'view_dashboard', 'view_kelas', 'view_pembimbing',
                'view_panduan', 'view_feedback', 'view_profil', 'edit_profil',
            ],
            'company_owner' => [
                'view_dashboard', 'view_kelas', 'view_pembimbing',
                'view_panduan', 'view_feedback', 'view_profil', 'edit_profil',
            ],
            'company_admin' => [
                'view_dashboard', 'view_kelas', 'view_pembimbing',
                'view_panduan', 'view_feedback', 'view_profil', 'edit_profil',
            ],
            'school_admin' => [
                'view_dashboard',
                'view_kelas', 'create_kelas', 'edit_kelas', 'delete_kelas',
                'view_pembimbing', 'create_pembimbing', 'edit_pembimbing', 'delete_pembimbing',
                'view_manajemen_user', 'create_manajemen_user', 'edit_manajemen_user',
                'view_isi_halaman', 'create_isi_halaman', 'edit_isi_halaman',
                'view_panduan', 'create_panduan', 'edit_panduan',
                'view_feedback', 'approve_feedback',
                'view_laporan', 'view_log_aktivitas',
                'view_profil', 'edit_profil',
            ],
            'university_admin' => [
                'view_dashboard',
                'view_kelas', 'create_kelas', 'edit_kelas', 'delete_kelas',
                'view_pembimbing',
                'view_manajemen_user', 'create_manajemen_user', 'edit_manajemen_user',
                'view_isi_halaman', 'create_isi_halaman', 'edit_isi_halaman',
                'view_panduan', 'create_panduan', 'edit_panduan',
                'view_laporan', 'view_log_aktivitas',
                'view_profil', 'edit_profil',
            ],
            'super_admin' => $permissions,
        ];

        // Create roles and sync their permissions
        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($perms);
        }

        // 3. Map legacy string roles to Spatie roles and assign to existing users
        $legacyRoleMap = [
            'super_admin' => 'super_admin',
            'school'      => 'school_admin',
            'student'     => 'siswa',
            'company'     => 'company_owner',
        ];

        $users = User::all();
        foreach ($users as $user) {
            if ($user->role && isset($legacyRoleMap[$user->role])) {
                $spatieRole = $legacyRoleMap[$user->role];
                if (!$user->hasRole($spatieRole)) {
                    $user->assignRole($spatieRole);
                }
            }
        }
    }
}

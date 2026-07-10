<?php
 
 namespace Tests\Feature;
 
 use App\Models\User;
 use Database\Seeders\RolePermissionSeeder;
 use Illuminate\Foundation\Testing\RefreshDatabase;
 use Laravel\Sanctum\Sanctum;
 use Spatie\Permission\Models\Permission;
 use Spatie\Permission\Models\Role;
 use Tests\TestCase;
 
 class RolePermissionTest extends TestCase
 {
     use RefreshDatabase;
 
     protected function setUp(): void
     {
         parent::setUp();
 
         // Run the seeder to create default roles and permissions
         $this->seed(RolePermissionSeeder::class);
     }
 
     /**
      * Test that roles and permissions seed properly.
      */
     public function test_roles_and_permissions_are_seeded(): void
     {
         $this->assertTrue(Role::where('name', 'super_admin')->exists());
         $this->assertTrue(Role::where('name', 'siswa')->exists());
         $this->assertTrue(Permission::where('name', 'view_kelas')->exists());
 
         $siswaRole = Role::findByName('siswa');
         $this->assertTrue($siswaRole->hasPermissionTo('view_kelas'));
         $this->assertFalse($siswaRole->hasPermissionTo('manage_roles'));
     }
 
     /**
      * Test user profile endpoint returns permissions.
      */
     public function test_profile_api_returns_permissions(): void
     {
         $user = User::factory()->create([
             'role' => 'student'
         ]);
         $user->assignRole('siswa');
 
         Sanctum::actingAs($user, ['student-access']);
 
         $response = $this->getJson('/api/v1/users/me/permissions');
 
         $response->assertStatus(200);
         $response->assertJsonStructure([
             'data' => [
                 'permissions'
             ]
         ]);
 
         $permissions = $response->json('data.permissions');
         $this->assertContains('view_kelas', $permissions);
         $this->assertNotContains('manage_roles', $permissions);
     }
 
     /**
      * Test that super_admin automatically gets all permissions.
      */
     public function test_super_admin_gets_all_permissions_automatically(): void
     {
         $user = User::factory()->create([
             'role' => 'super_admin'
         ]);
         $user->assignRole('super_admin');
 
         Sanctum::actingAs($user, ['admin-access']);
 
         $response = $this->getJson('/api/v1/users/me/permissions');
 
         $response->assertStatus(200);
         $permissions = $response->json('data.permissions');
         
         $allPermissionsCount = Permission::count();
         $this->assertCount($allPermissionsCount, $permissions);
     }
 
     /**
      * Test roles index endpoint.
      */
     public function test_admin_can_list_roles_and_permissions(): void
     {
         $user = User::factory()->create([
             'role' => 'super_admin'
         ]);
         $user->assignRole('super_admin');
 
         Sanctum::actingAs($user, ['admin-access']);
 
         $response = $this->getJson('/api/v1/system/roles');
 
         $response->assertStatus(200);
         $response->assertJsonStructure([
             'data' => [
                 '*' => [
                     'id',
                     'name',
                     'permissions'
                 ]
             ]
         ]);
     }
 
     /**
      * Test permissions list endpoint.
      */
     public function test_admin_can_list_all_permissions(): void
     {
         $user = User::factory()->create([
             'role' => 'super_admin'
         ]);
         $user->assignRole('super_admin');
 
         Sanctum::actingAs($user, ['admin-access']);
 
         $response = $this->getJson('/api/v1/system/permissions');
 
         $response->assertStatus(200);
         $response->assertJsonStructure([
             'data'
         ]);
         
         $this->assertNotEmpty($response->json('data'));
     }
 
     /**
      * Test updating role permissions.
      */
     public function test_admin_can_update_role_permissions(): void
     {
         $user = User::factory()->create([
             'role' => 'super_admin'
         ]);
         $user->assignRole('super_admin');
 
         Sanctum::actingAs($user, ['admin-access']);
 
         // Check original permissions for school_admin
         $schoolAdmin = Role::findByName('school_admin', 'web');
         $this->assertFalse($schoolAdmin->hasPermissionTo('manage_roles'));
 
         $response = $this->putJson('/api/v1/system/roles/school_admin/permissions', [
             'permissions' => ['view_kelas', 'manage_roles']
         ]);
 
         $response->assertStatus(200);
         
         // Reload role
         $schoolAdmin = Role::findByName('school_admin', 'web');
         $this->assertTrue($schoolAdmin->hasPermissionTo('manage_roles'));
         $this->assertTrue($schoolAdmin->hasPermissionTo('view_kelas'));
         $this->assertFalse($schoolAdmin->hasPermissionTo('view_laporan')); // was removed in sync
     }
 
     /**
      * Test authentication and authorization checks.
      */
     public function test_non_admin_cannot_access_role_management(): void
     {
         $user = User::factory()->create([
             'role' => 'student'
         ]);
         $user->assignRole('siswa');
 
         Sanctum::actingAs($user, ['student-access']);
 
         $responseIndex = $this->getJson('/api/v1/system/roles');
         $responseIndex->assertStatus(403);
 
         $responseUpdate = $this->putJson('/api/v1/system/roles/siswa/permissions', [
             'permissions' => ['view_kelas']
         ]);
         $responseUpdate->assertStatus(403);
     }
 }

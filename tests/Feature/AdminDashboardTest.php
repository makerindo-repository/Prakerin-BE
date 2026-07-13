<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Major;
use App\Models\Student;
use App\Models\JobOpening;
use App\Models\Field;
use App\Models\Duration;
use App\Models\Company;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_retrieve_admin_dashboard_with_matching_scores(): void
    {
        // 1. Create a super_admin user
        $user = User::factory()->create([
            'role' => 'super_admin'
        ]);
        $user->assignRole('super_admin');

        // 2. Set up some dummy majors, fields, companies, students and job openings in the database
        $major1 = Major::create([
            'name' => 'Rekayasa Perangkat Lunak',
            'level' => 'smk',
            'is_accepted' => true
        ]);
        $major2 = Major::create([
            'name' => 'Teknik Informatika',
            'level' => 'college',
            'is_accepted' => true
        ]);

        $field = Field::create([
            'name' => 'Web Development',
            'is_accepted' => true
        ]);

        $duration = Duration::create([
            'duration_value' => 3,
            'duration_unit' => 'month',
            'is_accepted' => true
        ]);

        $company = Company::create([
            'user_id' => User::factory()->create(['role' => 'company'])->id,
            'name' => 'Test Company',
            'address' => 'Test Address',
            'phone_number' => '12345678',
            'is_verified' => true
        ]);

        $job = JobOpening::create([
            'company_id' => $company->id,
            'field_id' => $field->id,
            'duration_id' => $duration->id,
            'title' => 'Web Developer Intern',
            'description' => 'Test description',
            'grade' => 'all',
            'type' => 'full_time',
            'location' => 'onsite',
            'is_paid' => true,
            'is_available' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'closing_date' => now()->addMonths(1)->toDateString(),
        ]);

        // Create a student for major1
        $studentUser = User::factory()->create(['role' => 'student']);
        Student::create([
            'user_id' => $studentUser->id,
            'name' => 'Student One',
            'major_id' => $major1->id,
            'status' => 'not_started'
        ]);

        // Acting as super admin
        Sanctum::actingAs($user, ['admin-access']);

        // 3. Make request
        $response = $this->getJson('/api/v1/admin/dashboard');

        // 4. Assertions
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary',
            'system_metrics',
            'regional_data',
            'placement_status',
            'insights',
            'recommendations',
            'recent_activities',
            'pre_internship_summary',
            'matching_scores' => [
                'smk',
                'mahasiswa'
            ]
        ]);

        // Check if our seeded majors exist in matching_scores
        $smkScores = $response->json('matching_scores.smk');
        $collegeScores = $response->json('matching_scores.mahasiswa');

        $this->assertNotEmpty($smkScores);
        $this->assertNotEmpty($collegeScores);

        $this->assertEquals('RPL', $smkScores[0]['label']);
        $this->assertEquals('Informatika', $collegeScores[0]['label']);
    }
}

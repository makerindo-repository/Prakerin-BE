<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Field;
use App\Models\Duration;
use App\Models\JobOpening;
use App\Models\Test as TestModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobOpeningUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_update_job_opening_successfully(): void
    {
        $user = User::factory()->create([
            'role' => 'company',
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'name' => 'PT Teknologi Maju',
            'email' => 'tech@example.com',
            'phone_number' => '08123456789',
            'address' => 'Jakarta',
            'status' => 'approved',
        ]);

        $field = Field::create([
            'name' => 'Web Development',
            'is_accepted' => true,
        ]);

        $duration = Duration::create([
            'duration_value' => 3,
            'duration_unit' => 'month',
            'is_accepted' => true,
        ]);

        $testItem = TestModel::create([
            'company_id' => $company->id,
            'title' => 'Tes Logika Pemrograman',
            'description' => 'Tes kemampuan logika dasar',
            'link' => 'https://example.com/test',
            'type' => 'theory',
            'is_accepted' => true,
        ]);

        $jobOpening = JobOpening::create([
            'company_id' => $company->id,
            'field_id' => $field->id,
            'duration_id' => $duration->id,
            'title' => 'Frontend Developer Intern',
            'description' => 'Lowongan magang frontend',
            'is_paid' => true,
            'grade' => 'smk',
            'type' => 'full_time',
            'location' => 'onsite',
            'qouta' => 3,
            'is_available' => true,
            'start_date' => '2026-10-01',
            'end_date' => '2027-01-01',
            'closing_date' => '2026-09-30',
        ]);

        Sanctum::actingAs($user, ['company-access']);

        $payload = [
            'title' => 'Frontend Developer Intern (Updated)',
            'type' => 'part_time',
            'location' => 'remote',
            'grade' => 'all',
            'field_id' => $field->id,
            'duration_id' => $duration->id,
            'description' => 'Updated description content',
            'qouta' => 5,
            'is_available' => true,
            'is_paid' => false,
            'start_date' => '2026-11-01',
            'closing_date' => '2026-10-31',
            'tests' => [$testItem->id],
        ];

        $response = $this->patchJson("/api/v1/job-openings/{$jobOpening->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Frontend Developer Intern (Updated)');
        $response->assertJsonPath('data.type', 'part_time');
        $response->assertJsonPath('data.location', 'remote');
        $response->assertJsonPath('data.qouta', 5);
        $response->assertJsonPath('data.is_paid', false);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'test',
                'tests',
            ]
        ]);

        $this->assertDatabaseHas('job_openings', [
            'id' => $jobOpening->id,
            'title' => 'Frontend Developer Intern (Updated)',
            'type' => 'part_time',
            'location' => 'remote',
            'qouta' => 5,
            'is_paid' => 0,
        ]);

        $this->assertDatabaseHas('job_opening_test', [
            'job_opening_id' => $jobOpening->id,
            'test_id' => $testItem->id,
        ]);
    }

    public function test_super_admin_can_update_any_job_opening(): void
    {
        $companyUser = User::factory()->create(['role' => 'company']);
        $company = Company::create([
            'user_id' => $companyUser->id,
            'name' => 'Perusahaan B',
            'email' => 'b@example.com',
            'phone_number' => '08123456788',
            'address' => 'Surabaya',
            'status' => 'approved',
        ]);

        $field = Field::create(['name' => 'Design', 'is_accepted' => true]);
        $duration = Duration::create(['duration_value' => 6, 'duration_unit' => 'month', 'is_accepted' => true]);

        $jobOpening = JobOpening::create([
            'company_id' => $company->id,
            'field_id' => $field->id,
            'duration_id' => $duration->id,
            'title' => 'UI/UX Designer',
            'description' => 'Original description',
            'is_paid' => false,
            'grade' => 'all',
            'type' => 'full_time',
            'location' => 'hybrid',
            'qouta' => 2,
            'is_available' => true,
            'start_date' => '2026-10-01',
            'end_date' => '2027-04-01',
            'closing_date' => '2026-09-25',
        ]);

        $adminUser = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($adminUser, ['admin-access', 'super_admin']);

        $response = $this->patchJson("/api/v1/job-openings/{$jobOpening->id}", [
            'title' => 'UI/UX Designer (Admin Modified)',
            'qouta' => 10,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'UI/UX Designer (Admin Modified)');
        $response->assertJsonPath('data.qouta', 10);
    }
}

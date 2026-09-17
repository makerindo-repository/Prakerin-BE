<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Field;
use App\Models\Duration;
use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOpeningIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_job_openings_without_10_limit_when_limit_not_specified(): void
    {
        $user = User::factory()->create(['role' => 'company']);
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

        // Create 15 job openings (more than the old limit of 10)
        for ($i = 1; $i <= 15; $i++) {
            JobOpening::create([
                'company_id' => $company->id,
                'field_id' => $field->id,
                'duration_id' => $duration->id,
                'title' => "Lowongan Posisi {$i}",
                'description' => "Deskripsi {$i}",
                'poster' => "poster_{$i}.png",
                'is_paid' => true,
                'grade' => 'all',
                'type' => 'full_time',
                'location' => 'onsite',
                'qouta' => 2,
                'is_available' => true,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(90)->toDateString(),
                'closing_date' => now()->addDays(30)->toDateString(),
            ]);
        }

        // 1. Without limit: should return all 15
        $responseAll = $this->getJson('/api/v1/job-openings');
        $responseAll->assertStatus(200);
        $responseAll->assertJsonCount(15, 'data');
        $responseAll->assertJsonPath('total', 15);

        // 2. With limit='all': should also return all 15
        $responseWithAll = $this->getJson('/api/v1/job-openings?limit=all');
        $responseWithAll->assertStatus(200);
        $responseWithAll->assertJsonCount(15, 'data');

        // 3. With explicit limit=6: should return exactly 6 and have last_page=3
        $responseLimit6 = $this->getJson('/api/v1/job-openings?limit=6');
        $responseLimit6->assertStatus(200);
        $responseLimit6->assertJsonCount(6, 'data');
        $responseLimit6->assertJsonPath('per_page', 6);
        $responseLimit6->assertJsonPath('last_page', 3);
        $responseLimit6->assertJsonPath('total', 15);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use App\Jobs\ProcessCvAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cv_upload_dispatches_process_cv_analysis_job(): void
    {
        Queue::fake();

        // 1. Create student user and assign abilities
        $user = User::factory()->create([
            'role' => 'student'
        ]);

        Sanctum::actingAs($user, ['student-access']);

        // 2. Set dynamic settings so API is enabled and key exists
        Setting::create([
            'key' => 'ai_provider',
            'value' => 'gemini'
        ]);
        config(['gemini.api_key' => 'fake-api-key']);

        // 3. Create a dummy PDF file
        $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

        // 4. Send upload request
        $response = $this->postJson('/api/v1/ai-analytics', [
            'uploaded_file' => $file
        ]);

        // 5. Assert successful background dispatch response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'file_name',
                    'file_path',
                    'analysis_result' => [
                        'status'
                    ]
                ]
            ]);

        $this->assertEquals('processing', $response->json('data.analysis_result.status'));

        // 6. Assert queue job was pushed
        Queue::assertPushed(ProcessCvAnalysis::class);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AiAnalytic;
use App\Models\JobOpening;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema;
use Gemini\Data\Blob;
use Gemini\Enums\DataType;
use Gemini\Enums\ResponseMimeType;
use Gemini\Enums\MimeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiAnalyticsController extends Controller
{
    /**
     * Run AI analysis on the uploaded CV/Resume PDF.
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'uploaded_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $aiProvider = \App\Models\Setting::getVal('ai_provider', 'gemini');
        if ($aiProvider === 'none') {
            return response()->json(['message' => 'Layanan AI Analytics dinonaktifkan oleh administrator.'], 403);
        }

        if (!config('gemini.api_key')) {
            return response()->json([
                'error_type' => 'missing_api_key',
                'message' => 'Layanan AI Analytics belum siap. Kunci API Gemini belum dikonfigurasi di menu Pengaturan Sistem.'
            ], 500);
        }

        $pdfFile = $request->file('uploaded_file');
        
        // Store file and save record
        $filename = now()->format('Ymd_His') . '_' . uniqid() . '.' . $pdfFile->getClientOriginalExtension();
        $path = $pdfFile->storeAs('ai-analytics', $filename);

        $analyticRecord = AiAnalytic::create([
            'user_id' => auth()->id(),
            'file_name' => $pdfFile->getClientOriginalName(),
            'file_path' => $path,
            'analysis_result' => [
                'status' => 'processing'
            ]
        ]);

        // Dispatch background job
        \App\Jobs\ProcessCvAnalysis::dispatch($analyticRecord->id);

        // Force-run a background queue worker to process the job immediately (workaround for no supervisor/terminal access)
        $this->triggerQueueWorker();

        return response()->json([
            'message' => 'Analisis CV sedang diproses di latar belakang.',
            'data' => $analyticRecord
        ]);
    }

    /**
     * Start a queue worker process in the background.
     */
    private function triggerQueueWorker()
    {
        $command = "php " . base_path('artisan') . " queue:work --stop-when-empty --tries=1";
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows background execution
            pclose(popen("start /B " . $command . " > NUL 2>&1", "r"));
        } else {
            // Linux background execution
            pclose(popen($command . " > /dev/null 2>&1 &", "r"));
        }
    }

    /**
     * Get the latest AI recommendation report for the authenticated student.
     */
    public function latest()
    {
        $latest = AiAnalytic::where('user_id', auth()->id())
            ->latest()
            ->first();

        if (!$latest) {
            return response()->json([
                'message' => 'Belum ada hasil analisis untuk akun ini.',
                'data' => null
            ], 200);
        }

        return response()->json([
            'data' => $latest
        ]);
    }

    /**
     * Get history of AI analysis.
     */
    public function index()
    {
        $user = auth()->user();
        
        // Admins can see all, students only see their own
        if ($user->role === 'super_admin') {
            $history = AiAnalytic::with('user')->latest()->get();
        } else {
            $history = AiAnalytic::where('user_id', $user->id)->latest()->get();
        }

        return response()->json([
            'data' => $history
        ]);
    }

    /**
     * Delete an analysis.
     */
    public function destroy($id)
    {
        $analytic = AiAnalytic::findOrFail($id);

        // Check ownership
        if (auth()->user()->role !== 'super_admin' && $analytic->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Delete file
        if (Storage::exists($analytic->file_path)) {
            Storage::delete($analytic->file_path);
        }

        $analytic->delete();

        return response()->json([
            'message' => 'Riwayat analisis berhasil dihapus.'
        ]);
    }

    /**
     * Get uploaded PDF file.
     */
    public function downloadPdf($id)
    {
        $analytic = AiAnalytic::findOrFail($id);

        // Check ownership
        if (auth()->user()->role !== 'super_admin' && $analytic->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $path = storage_path('app/' . $analytic->file_path);

        if (!file_exists($path)) {
            return response()->json(['message' => 'File resume tidak ditemukan.'], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $analytic->file_name . '"'
        ]);
    }

    /**
     * Update the review status of an AI credibility check (admin only).
     */
    public function review(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:APPROVED,REJECTED'
        ]);

        $analytic = AiAnalytic::findOrFail($id);

        $result = $analytic->analysis_result;
        if (!isset($result['credibility'])) {
            return response()->json(['message' => 'Analisis kredibilitas tidak ditemukan untuk record ini.'], 404);
        }

        $credibility = $result['credibility'];
        
        // Backup original action before updating
        $originalAction = $credibility['original_action'] ?? ($credibility['action'] ?? 'REVIEW');
        $credibility['original_action'] = $originalAction;
        $credibility['originalAction'] = $originalAction;

        $newAction = $request->action; // APPROVED or REJECTED
        $credibility['action'] = $newAction;
        $credibility['review_required'] = false;
        $credibility['reviewRequired'] = false;
        $credibility['reviewed_by'] = auth()->user()->name ?? 'Administrator';
        $credibility['reviewed_at'] = now()->toIso8601String();

        $result['credibility'] = $credibility;
        $analytic->analysis_result = $result;
        $analytic->save();

        // Create log of activity
        try {
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'resource_type' => 'AiAnalytic',
                'resource_id' => $analytic->id,
                'resource_name' => $analytic->file_name,
                'description' => 'Recruiter approved/rejected CV credibility review: ' . $newAction
            ]);
        } catch (\Throwable $logEx) {
            Log::warning('Failed to log review activity: ' . $logEx->getMessage());
        }

        return response()->json([
            'message' => 'Keputusan review berhasil disimpan.',
            'data' => $analytic
        ]);
    }

    /**
     * Search internships using AI.
     */
    public function aiSearch(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:500'
        ]);

        $userQuery = $request->input('query');

        $aiProvider = \App\Models\Setting::getVal('ai_provider', 'gemini');
        if ($aiProvider === 'none') {
            return response()->json(['message' => 'Layanan AI Analytics / AI Search dinonaktifkan oleh administrator.'], 403);
        }

        if (!config('gemini.api_key')) {
            return response()->json([
                'error_type' => 'missing_api_key',
                'message' => 'Layanan AI Search belum siap. Kunci API Gemini belum dikonfigurasi di menu Pengaturan Sistem.'
            ], 500);
        }

        // Get all active job openings
        $jobOpenings = JobOpening::where('is_available', true)->with(['company', 'field', 'duration'])->get();

        if ($jobOpenings->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada lowongan magang aktif saat ini di sistem.',
                'data' => []
            ]);
        }

        $jobsText = "";
        foreach ($jobOpenings as $job) {
            $descriptionText = is_string($job->description) ? $job->description : '';
            if ($job->description && is_array($job->description) && isset($job->description['blocks'])) {
                $descriptionText = "";
                foreach ($job->description['blocks'] as $block) {
                    if ($block['type'] === 'paragraph' || $block['type'] === 'header') {
                        $descriptionText .= ($block['data']['text'] ?? '') . "\n";
                    } elseif ($block['type'] === 'list') {
                        foreach ($block['data']['items'] ?? [] as $item) {
                            $itemText = is_array($item) ? json_encode($item) : (string)$item;
                            $descriptionText .= "- " . $itemText . "\n";
                        }
                    }
                }
            }
            $jobsText .= "ID: {$job->id}\nTitle: {$job->title}\nCompany: " . ($job->company?->name ?? 'Unknown Company') . "\nDescription: {$descriptionText}\n-----------------------------------\n";
        }

        $prompt = "Anda adalah asisten AI pencari magang untuk Prakerin.ID.
Tugas Anda adalah memproses kueri pencarian siswa dan mencocokkannya dengan lowongan magang aktif yang tersedia di bawah ini.
Kueri Pencarian Pengguna: '$userQuery'

Berikut adalah daftar lowongan magang aktif di sistem:
$jobsText

Pilihlah maksimal 5 lowongan magang yang paling relevan dengan kueri tersebut.
Hasilkan response dalam format JSON yang berisi array dari objek rekomendasi.
Response harus dalam bahasa Indonesia yang ramah, sopan, dan informatif.";

        $schema = new Schema(
            type: DataType::ARRAY,
            items: new Schema(
                type: DataType::OBJECT,
                properties: [
                    'job_opening_id' => new Schema(type: DataType::STRING),
                    'title' => new Schema(type: DataType::STRING),
                    'company_name' => new Schema(type: DataType::STRING),
                    'match_score' => new Schema(type: DataType::INTEGER),
                    'explanation' => new Schema(type: DataType::STRING),
                ],
                required: ['job_opening_id', 'title', 'company_name', 'match_score', 'explanation']
            )
        );

        try {
            $result = Gemini::generativeModel("gemini-3.1-flash-lite")->withGenerationConfig(
                generationConfig: new GenerationConfig(
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                    responseSchema: $schema
                )
            )->generateContent($prompt);

            $responseJson = $result->json();

            // Log activity
            try {
                \App\Models\ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'search',
                    'resource_type' => 'JobOpening',
                    'resource_id' => null,
                    'resource_name' => substr($userQuery, 0, 50),
                    'description' => 'User searched internships using AI with query: ' . $userQuery
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('Failed to log search activity: ' . $logEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $responseJson
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Search Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pencarian berbasis AI: ' . $e->getMessage()
            ], 500);
        }
    }
}

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
        // 1. Validate the file
        $request->validate([
            'uploaded_file' => 'required|file|mimes:pdf|max:10240', // Max 10MB PDF
        ]);

        // 2. Check Gemini API key
        if (!config('gemini.api_key')) {
            return response()->json([
                'error_type' => 'missing_api_key',
                'message' => 'Layanan AI Analytics belum siap. API Key Gemini belum dikonfigurasi di file .env backend (GEMINI_API_KEY).'
            ], 500);
        }

        $pdfFile = $request->file('uploaded_file');

        // 3. Fetch active job openings and format them for the prompt
        $jobOpenings = JobOpening::where('is_available', true)->with('company')->get();

        $jobsText = "";
        if ($jobOpenings->isEmpty()) {
            $jobsText = "Saat ini belum ada lowongan magang aktif yang tersedia di sistem.\n";
        } else {
            foreach ($jobOpenings as $job) {
                $descriptionText = "";
                if ($job->description && is_array($job->description) && isset($job->description['blocks'])) {
                    foreach ($job->description['blocks'] as $block) {
                        if ($block['type'] === 'paragraph' || $block['type'] === 'header') {
                            $descriptionText .= ($block['data']['text'] ?? '') . "\n";
                        } elseif ($block['type'] === 'list') {
                            foreach ($block['data']['items'] ?? [] as $item) {
                                $itemText = "";
                                if (is_array($item)) {
                                    if (isset($item['content'])) {
                                        $itemText = is_array($item['content']) ? json_encode($item['content']) : (string)$item['content'];
                                    } else {
                                        $itemText = json_encode($item);
                                    }
                                } else {
                                    $itemText = (string)$item;
                                }
                                $descriptionText .= "- " . $itemText . "\n";
                            }
                        }
                    }
                } else {
                    $descriptionText = is_string($job->description) ? $job->description : '';
                }

                $jobsText .= "ID: {$job->id}\n";
                $jobsText .= "Title: {$job->title}\n";
                $jobsText .= "Company: " . ($job->company?->name ?? 'Unknown Company') . "\n";
                $jobsText .= "Location: {$job->location}\n";
                $jobsText .= "Grade Requirements: {$job->grade}\n";
                $jobsText .= "Description & Requirements:\n{$descriptionText}\n";
                $jobsText .= "-----------------------------------\n";
            }
        }

        // Determine student grade context
        $user = auth()->user();
        $studentGrade = 'school';
        if ($user->student && $user->student->school) {
            $studentGrade = $user->student->school->type; // 'school' or 'university'
        }

        // 4. Construct prompt
        $prompt = "Anda adalah asisten AI karir untuk Prakerin.ID.
Tugas Anda adalah menganalisis CV/Resume berbentuk PDF yang diunggah oleh siswa/mahasiswa dan memberikan rekomendasi lowongan magang yang paling sesuai berdasarkan daftar lowongan aktif yang tersedia di bawah ini.

Kandidat saat ini menempuh pendidikan tingkat: " . ($studentGrade === 'university' ? 'Perguruan Tinggi (Mahasiswa)' : 'Sekolah Menengah (Siswa)'). ". Harap prioritaskan lowongan magang yang cocok untuk tingkat ini.

Berikut adalah daftar lowongan magang aktif yang tersedia di sistem:
$jobsText

Harap analisis CV PDF yang dilampirkan dan hasilkan response dalam format JSON yang terstruktur. Jika tidak ada lowongan yang aktif yang cocok, Anda diperbolehkan membuat rekomendasi umum (general recommendation) mengenai tipe posisi magang yang cocok dan menetapkan 'is_general_recommendation' menjadi true.

Skema JSON yang harus Anda hasilkan wajib memiliki properti berikut:
- profile_summary (objek berisi name, education, skills[], strengths[])
- recommendations (array berisi job_opening_id, title, company_name, match_score, reasoning, is_general_recommendation)
- improvement_suggestions (array of string)";

        // 5. Define schema
        $schema = new Schema(
            type: DataType::OBJECT,
            properties: [
                'profile_summary' => new Schema(
                    type: DataType::OBJECT,
                    properties: [
                        'name' => new Schema(type: DataType::STRING),
                        'education' => new Schema(type: DataType::STRING),
                        'skills' => new Schema(type: DataType::ARRAY, items: new Schema(type: DataType::STRING)),
                        'strengths' => new Schema(type: DataType::ARRAY, items: new Schema(type: DataType::STRING)),
                    ],
                    required: ['name', 'education', 'skills', 'strengths']
                ),
                'recommendations' => new Schema(
                    type: DataType::ARRAY,
                    items: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'job_opening_id' => new Schema(type: DataType::STRING, nullable: true),
                            'title' => new Schema(type: DataType::STRING),
                            'company_name' => new Schema(type: DataType::STRING),
                            'match_score' => new Schema(type: DataType::INTEGER),
                            'reasoning' => new Schema(type: DataType::STRING),
                            'is_general_recommendation' => new Schema(type: DataType::BOOLEAN),
                        ],
                        required: ['job_opening_id', 'title', 'company_name', 'match_score', 'reasoning', 'is_general_recommendation']
                    )
                ),
                'improvement_suggestions' => new Schema(
                    type: DataType::ARRAY,
                    items: new Schema(type: DataType::STRING)
                )
            ],
            required: ['profile_summary', 'recommendations', 'improvement_suggestions']
        );

        // 6. Call Gemini (with automatic fallback to gemini-3.1-flash-lite on quota/limit errors)
        try {
            $result = Gemini::generativeModel("gemini-3.5-flash")->withGenerationConfig(
                generationConfig: new GenerationConfig(
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                    responseSchema: $schema
                )
            )->generateContent(
                $prompt,
                new Blob(
                    mimeType: MimeType::APPLICATION_PDF,
                    data: base64_encode(file_get_contents($pdfFile->getRealPath()))
                )
            );

            $jsonResponse = $result->json();
        } catch (\Throwable $e) {
            $errMessage = $e->getMessage();
            if (str_contains(strtolower($errMessage), 'quota') || str_contains(strtolower($errMessage), 'limit')) {
                Log::info('Gemini 3.5 Flash limit exceeded or restricted. Falling back to Gemini 3.1 Flash Lite.');
                try {
                    $result = Gemini::generativeModel("gemini-3.1-flash-lite")->withGenerationConfig(
                        generationConfig: new GenerationConfig(
                            responseMimeType: ResponseMimeType::APPLICATION_JSON,
                            responseSchema: $schema
                        )
                    )->generateContent(
                        $prompt,
                        new Blob(
                            mimeType: MimeType::APPLICATION_PDF,
                            data: base64_encode(file_get_contents($pdfFile->getRealPath()))
                        )
                    );

                    $jsonResponse = $result->json();
                } catch (\Throwable $fallbackException) {
                    Log::error('Gemini 3.1 Flash Lite Fallback Error: ' . $fallbackException->getMessage());
                    return response()->json([
                        'message' => 'Gagal memproses resume menggunakan AI Gemini (Limit Kuota Terlampaui): ' . $fallbackException->getMessage()
                    ], 500);
                }
            } else {
                Log::error('Gemini AI Analytics Error: ' . $errMessage, ['trace' => $e->getTraceAsString()]);
                return response()->json([
                    'message' => 'Gagal memproses resume menggunakan AI Gemini: ' . $errMessage
                ], 500);
            }
        }

        // 7. Store file and save record
        $filename = now()->format('Ymd_His') . '_' . uniqid() . '.' . $pdfFile->getClientOriginalExtension();
        $path = $pdfFile->storeAs('ai-analytics', $filename);

        $analyticRecord = AiAnalytic::create([
            'user_id' => $user->id,
            'file_name' => $pdfFile->getClientOriginalName(),
            'file_path' => $path,
            'analysis_result' => $jsonResponse
        ]);

        return response()->json([
            'message' => 'Analisis CV berhasil diselesaikan oleh Gemini AI.',
            'data' => $analyticRecord
        ]);
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
}

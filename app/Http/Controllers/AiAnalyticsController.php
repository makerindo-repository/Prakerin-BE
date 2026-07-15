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
        // Increase maximum execution time for AI processing
        @set_time_limit(120);

        // 1. Validate the file
        $request->validate([
            'uploaded_file' => 'required|file|mimes:pdf|max:10240', // Max 10MB PDF
        ]);

        // 2. Check AI Provider and Gemini API key
        $aiProvider = \App\Models\Setting::getVal('ai_provider', 'gemini');
        if ($aiProvider === 'none') {
            return response()->json([
                'message' => 'Layanan AI Analytics dinonaktifkan oleh administrator.'
            ], 403);
        }

        if (!config('gemini.api_key')) {
            return response()->json([
                'error_type' => 'missing_api_key',
                'message' => 'Layanan AI Analytics belum siap. Kunci API Gemini belum dikonfigurasi di menu Pengaturan Sistem.'
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

Tugas Anda juga mencakup menganalisis kredibilitas dari CV/Resume yang diunggah. Kredibilitas dihitung secara terpisah untuk 3 komponen (aspek) berikut (skor awal masing-masing aspek adalah 100, batas minimum 0):

1. Timeline Validation (Bobot 30%, Skor awal 100):
   - Periksa tanggal kerja/pendidikan yang tumpang tindih (Overlap). Kurangi 15 poin untuk setiap overlap dan tambahkan objek flag di array flags dengan type: 'TIMELINE_OVERLAP', severity: 'high', dan berikan pesan 'message' detail tentang perusahaan yang bertabrakan.
   - Periksa job hopping: Jika kandidat memiliki lebih dari 5 pekerjaan dalam 3 tahun terakhir, kurangi 10 poin dan tambahkan flag type: 'JOB_HOPPING', severity: 'medium', beserta message detail.
   - Periksa kesesuaian lama pengalaman (Experience mismatch): Jika jumlah tahun pengalaman yang diklaim secara eksplisit berbeda lebih dari 5 tahun dengan total tahun dari daftar riwayat kerja, kurangi 20 poin dan tambahkan flag type: 'EXPERIENCE_MISMATCH', severity: 'high'.

2. Claim Plausibility Check (Bobot 40%, Skor awal 100):
   - Periksa klaim posisi/sertifikat/penghargaan yang mencurigakan (Suspicious Claims). Jika ada klaim posisi tidak realistis seperti 'founder of' Microsoft/Google, 'inventor of the Internet', atau 'Nobel Prize', kurangi poin (founder: -28.5, ceo: -27, nobel prize: -29.7, inventor: -28.5, key developer: -21, built: -18) dan berikan flag type: 'SUSPICIOUS_CLAIM' dengan severity 'high' atau 'medium' beserta detail claim dan context kalimatnya.
   - Periksa kepadatan pencapaian (High achievement density): Jika kandidat mengklaim pencapaian yang tidak masuk akal (misalnya > 30 bullet points atau klaim prestasi yang terlalu padat dalam jangka waktu pendek), kurangi 15 poin dan tambahkan flag type: 'HIGH_ACHIEVEMENT_DENSITY', severity: 'medium'.
   - Periksa inflasi keahlian (Expertise inflation): Jika keahlian tingkat expert/senior terlalu banyak dibanding lama pengalaman (misalnya jumlah skill expert > setengah dari total tahun pengalaman), kurangi 15 poin dan tambahkan flag type: 'EXPERTISE_INFLATION', severity: 'medium'.

3. Consistency Check (Bobot 30%, Skor awal 100):
   - Periksa ketidakselarasan skill dengan peran (Skill-to-role mismatch): Jika mengklaim expert di suatu skill teknis (misal Python/Kubernetes) tapi tidak ada riwayat pekerjaan/pendidikan/proyek yang relevan, kurangi 5 poin per mismatch dan tambahkan flag type: 'SKILL_ROLE_MISMATCH', severity: 'low'.
   - Periksa kontradiksi:
     - Jika masa studi full-time bertabrakan dengan pekerjaan full-time (overlap), kurangi 20 poin dan tambahkan flag type: 'CONTRADICTION' (message: 'Full-time study overlaps with full-time employment') dengan severity 'medium'.
     - Jika progression karir tidak logis (misal CEO jadi Intern), kurangi 20 poin dan tambahkan flag type: 'CONTRADICTION' (message: 'Career progression seems unrealistic (e.g., CEO → Intern)') dengan severity 'medium'.
   - Periksa buzzword overload: Jika menggunakan 4+ buzzwords klise (synergy, leverage, disrupt, blockchain, AI) di summary/bio, kurangi 8 poin dan tambahkan flag type: 'BUZZWORD_OVERLOAD', severity: 'low'.

Skor Kredibilitas Akhir = (Timeline Score * 0.3) + (Plausibility Score * 0.4) + (Consistency Score * 0.3)
Tentukan level dan action:
- Skor < 33: level = 'LOW', action = 'REJECT', review_required = false.
- Skor 34 - 66: level = 'MEDIUM', action = 'REVIEW', review_required = true.
- Skor >= 67: level = 'HIGH', action = 'PROCEED', review_required = false.

Skema JSON yang harus Anda hasilkan wajib memiliki properti berikut:
- profile_summary (objek berisi name, education, skills[], strengths[])
- recommendations (array berisi job_opening_id, title, company_name, match_score, reasoning, is_general_recommendation)
- improvement_suggestions (array of string)
- credibility (objek berisi score, level, action, review_required, flags[], flags_by_level{}, summary{})";

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
                ),
                'credibility' => new Schema(
                    type: DataType::OBJECT,
                    properties: [
                        'score' => new Schema(type: DataType::INTEGER),
                        'level' => new Schema(type: DataType::STRING),
                        'action' => new Schema(type: DataType::STRING),
                        'review_required' => new Schema(type: DataType::BOOLEAN),
                        'flags' => new Schema(
                            type: DataType::ARRAY,
                            items: new Schema(
                                type: DataType::OBJECT,
                                properties: [
                                    'type' => new Schema(type: DataType::STRING),
                                    'severity' => new Schema(type: DataType::STRING),
                                    'message' => new Schema(type: DataType::STRING),
                                    'suggestion' => new Schema(type: DataType::STRING, nullable: true),
                                    'claim' => new Schema(type: DataType::STRING, nullable: true),
                                    'context' => new Schema(type: DataType::STRING, nullable: true),
                                ],
                                required: ['type', 'severity', 'message']
                            )
                        ),
                        'flags_by_level' => new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'critical' => new Schema(type: DataType::INTEGER),
                                'warning' => new Schema(type: DataType::INTEGER),
                                'info' => new Schema(type: DataType::INTEGER),
                            ],
                            required: ['critical', 'warning', 'info']
                        ),
                        'summary' => new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'timeline_score' => new Schema(type: DataType::INTEGER),
                                'plausibility_score' => new Schema(type: DataType::INTEGER),
                                'consistency_score' => new Schema(type: DataType::INTEGER),
                            ],
                            required: ['timeline_score', 'plausibility_score', 'consistency_score']
                        )
                    ],
                    required: ['score', 'level', 'action', 'review_required', 'flags', 'flags_by_level', 'summary']
                )
            ],
            required: ['profile_summary', 'recommendations', 'improvement_suggestions', 'credibility']
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

        // Post-processing/correction on the credibility metrics to ensure determinism and compatibility
        if (isset($jsonResponse['credibility'])) {
            $credibility = $jsonResponse['credibility'];
            
            $timelineScore = $credibility['summary']['timeline_score'] ?? 100;
            $plausibilityScore = $credibility['summary']['plausibility_score'] ?? 100;
            $consistencyScore = $credibility['summary']['consistency_score'] ?? 100;
            
            // Re-calculate math to ensure exact weighted score
            $score = ($timelineScore * 0.30) + ($plausibilityScore * 0.40) + ($consistencyScore * 0.30);
            $score = max(0, min(100, (int)round($score)));
            
            $level = 'HIGH';
            $action = 'PROCEED';
            $reviewRequired = false;
            
            if ($score < 33) {
                $level = 'LOW';
                $action = 'REJECT';
            } elseif ($score < 67) {
                $level = 'MEDIUM';
                $action = 'REVIEW';
                $reviewRequired = true;
            }
            
            $credibility['score'] = $score;
            $credibility['level'] = $level;
            $credibility['action'] = $action;
            $credibility['review_required'] = $reviewRequired;
            
            // Support camelCase for frontend compatibility
            $credibility['credibilityScore'] = $score;
            $credibility['credibilityLevel'] = $level;
            $credibility['reviewRequired'] = $reviewRequired;
            
            // Recalculate flags count
            $flags = $credibility['flags'] ?? [];
            $critical = 0;
            $warning = 0;
            $info = 0;
            foreach ($flags as $flag) {
                $severity = strtolower($flag['severity'] ?? '');
                if ($severity === 'high' || $severity === 'critical') {
                    $critical++;
                } elseif ($severity === 'medium' || $severity === 'warning') {
                    $warning++;
                } else {
                    $info++;
                }
            }
            
            $flagsByLevel = [
                'critical' => $critical,
                'warning' => $warning,
                'info' => $info
            ];
            
            $credibility['flags_by_level'] = $flagsByLevel;
            $credibility['flagsByLevel'] = $flagsByLevel;
            
            $jsonResponse['credibility'] = $credibility;
            
            // Enforce: Low Credibility (0-33%) has no recommendations
            if ($action === 'REJECT') {
                $jsonResponse['recommendations'] = [];
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
}

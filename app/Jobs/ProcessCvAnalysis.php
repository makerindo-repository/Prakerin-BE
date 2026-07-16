<?php

namespace App\Jobs;

use App\Models\AiAnalytic;
use App\Models\JobOpening;
use App\Models\Setting;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema;
use Gemini\Data\Blob;
use Gemini\Enums\DataType;
use Gemini\Enums\ResponseMimeType;
use Gemini\Enums\MimeType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessCvAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Timeout for the queue job process (in seconds)
    public $timeout = 180;

    protected $analyticId;

    public function __construct($analyticId)
    {
        $this->analyticId = $analyticId;
    }

    public function handle()
    {
        $analytic = AiAnalytic::find($this->analyticId);
        if (!$analytic) {
            return;
        }

        try {
            $user = $analytic->user;
            
            // Build the prompt and schema
            $jobOpenings = JobOpening::where('is_available', true)->with('company')->get();
            $jobsText = "";
            if ($jobOpenings->isEmpty()) {
                $jobsText = "Saat ini belum ada lowongan magang aktif yang tersedia di sistem.\n";
            } else {
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

                    $jobsText .= "ID: {$job->id}\nTitle: {$job->title}\nCompany: " . ($job->company?->name ?? 'Unknown Company') . "\nDescription:\n{$descriptionText}\n-----------------------------------\n";
                }
            }

            $studentGrade = ($user && $user->student && $user->student->school) ? $user->student->school->type : 'school';
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
                                'job_opening_id' => new Schema(type: DataType::STRING),
                                'title' => new Schema(type: DataType::STRING),
                                'company_name' => new Schema(type: DataType::STRING),
                                'match_score' => new Schema(type: DataType::INTEGER),
                                'reasoning' => new Schema(type: DataType::STRING),
                                'is_general_recommendation' => new Schema(type: DataType::BOOLEAN),
                            ],
                            required: ['title', 'company_name', 'match_score', 'reasoning']
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

            // Fetch PDF path and generate
            $pdfPath = storage_path('app/private/' . $analytic->file_path);
            if (!file_exists($pdfPath)) {
                // Try fallback to local disk path
                $pdfPath = storage_path('app/' . $analytic->file_path);
            }

            $result = Gemini::generativeModel("gemini-3.5-flash")->withGenerationConfig(
                generationConfig: new GenerationConfig(
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                    responseSchema: $schema
                )
            )->generateContent(
                $prompt,
                new Blob(
                    mimeType: MimeType::APPLICATION_PDF,
                    data: base64_encode(file_get_contents($pdfPath))
                )
            );

            $jsonResponse = $result->json();

            // Run normal post-processing formatting rules
            if (is_object($jsonResponse) || is_array($jsonResponse)) {
                $jsonResponse = json_decode(json_encode($jsonResponse), true);
            }

            if (isset($jsonResponse['credibility'])) {
                $credibility = $jsonResponse['credibility'];
                $timelineScore = $credibility['summary']['timeline_score'] ?? 100;
                $plausibilityScore = $credibility['summary']['plausibility_score'] ?? 100;
                $consistencyScore = $credibility['summary']['consistency_score'] ?? 100;
                
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
                $credibility['credibilityScore'] = $score;
                $credibility['credibilityLevel'] = $level;
                $credibility['reviewRequired'] = $reviewRequired;
                
                $flags = $credibility['flags'] ?? [];
                $critical = 0; $warning = 0; $info = 0;
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
                
                $flagsByLevel = ['critical' => $critical, 'warning' => $warning, 'info' => $info];
                $credibility['flags_by_level'] = $flagsByLevel;
                $credibility['flagsByLevel'] = $flagsByLevel;
                $jsonResponse['credibility'] = $credibility;
                
                if ($action === 'REJECT') {
                    $jsonResponse['recommendations'] = [];
                }
            }

            $analytic->update([
                'analysis_result' => $jsonResponse
            ]);

        } catch (\Throwable $e) {
            Log::error('Gemini Queue Analysis Error: ' . $e->getMessage());
            
            $analytic->update([
                'analysis_result' => [
                    'status' => 'failed',
                    'message' => 'Gagal memproses resume menggunakan AI Gemini: ' . $e->getMessage()
                ]
            ]);
        }
    }
}

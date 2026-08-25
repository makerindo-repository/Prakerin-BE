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
            $jobOpenings = JobOpening::where('is_available', true)
                ->where('closing_date', '>=', now()->toDateString())
                ->with(['company.cityRegency', 'field'])
                ->get();
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

                    $locationStr = $job->location ?? 'onsite';
                    $compCity = $job->company?->cityRegency?->name ?? 'Bandung';
                    $jobsText .= "ID: {$job->id}\nTitle: {$job->title}\nCompany: " . ($job->company?->name ?? 'Unknown Company') . "\nLocation: {$compCity} ({$locationStr})\nDescription:\n{$descriptionText}\n-----------------------------------\n";
                }
            }

            $studentGrade = ($user && $user->student && $user->student->school) ? $user->student->school->type : 'school';
            $prompt = "Anda adalah asisten AI karir & rekrutmen profesional untuk Prakerin.ID.
Tugas Anda adalah menganalisis dokumen CV/Resume berbentuk PDF yang diunggah oleh kandidat (siswa/mahasiswa) secara komprehensif, mendalam, dan objektif untuk menghasilkan dashboard analisis karir yang lengkap dan mencocokkannya dengan daftar lowongan magang aktif yang tersedia di bawah ini.

Kandidat saat ini menempuh pendidikan tingkat: " . ($studentGrade === 'university' ? 'Perguruan Tinggi (Mahasiswa)' : 'Sekolah Menengah (Siswa)'). ". Harap prioritaskan lowongan magang yang cocok untuk tingkat ini.

Berikut adalah daftar lowongan magang aktif yang tersedia di sistem:
$jobsText

Instruksi Analisis:
1. Ekstraksi Data Profil Kandidat:
   - name: Nama lengkap kandidat yang tertera pada CV
   - initials: Inisial 2 huruf nama kandidat (misal: 'RS')
   - education: Jenjang dan jurusan pendidikan (misal: 'Sarjana Komputer, Teknik Informatika' atau 'SMK Rekayasa Perangkat Lunak')
   - institution_years: Nama institusi/sekolah/universitas beserta tahun kelulusan/studi (misal: 'STMIK Mardira Indonesia (2020-2024)')
   - target_role: Peran karir target yang paling cocok untuk kandidat berdasarkan keahliannya (misal: 'Web & IoT Developer', 'Fullstack Developer', 'Frontend Developer', dsb.)
   - skills: Array string keahlian teknis dan fungsional kandidat (misal: ['React', 'Next.js', 'JavaScript', 'Node.js', 'Express.js', 'Vue.js', 'PHP', 'Laravel', 'IoT (MQTT)', 'Arduino', 'MySQL', 'Git', 'GitHub', 'REST API'])
   - portfolio_url: URL portofolio/website pribadi kandidat jika ada di CV (misal: 'raihansaprudin.dev'), atau string kosong jika tidak ada
   - github_url: URL/username GitHub kandidat jika ada di CV (misal: 'github.com/raihansaprudin'), atau string kosong jika tidak ada
   - strengths: Array string berisi 4 poin kekuatan utama kandidat (misal: ['Pengalaman proyek web dan IoT yang relevan', 'Penguasaan full-stack (MERN + Node.js)', 'Portofolio dan GitHub tersedia', 'Penggunaan Git untuk version control'])

2. Penilaian 4 Metrik Utama (Skor 0-100):
   - ats_score: Skor kompatibilitas ATS CV (format, layout, kata kunci, struktur).
   - ats_quality: Label kualitas ATS ('Sangat Baik' jika >=85, 'Baik' jika 70-84, 'Cukup' jika 55-69, 'Perlu Ditingkatkan' jika <55)
   - competency_match_score: Persentase keselarasan kompetensi kandidat dengan kebutuhan industri magang saat ini (0-100).
   - competency_quality: Label kualitas ('Sangat Baik', 'Baik', 'Cukup', 'Perlu Ditingkatkan')
   - internship_readiness_score: Persentase kesiapan magang kandidat (0-100).
   - readiness_quality: Label kualitas ('Sangat Baik', 'Baik', 'Cukup', 'Perlu Ditingkatkan')
   - verification_score: Skor verifikasi konsistensi data CV (0-100, default 100 jika konsisten).
   - verification_status: Status ('Terverifikasi', 'Sebagian Terverifikasi', 'Perlu Verifikasi')
   - verification_note: Catatan verifikasi (misal: 'Identitas dan data pendidikan telah diverifikasi.')

3. Temuan Perbaikan CV (improvements):
   - Hasilkan 3-4 temuan kekurangan atau area perbaikan pada CV kandidat.
   - Format: array objek { issue: string, priority: 'Tinggi' | 'Sedang' | 'Rendah' }
   - Contoh: issue 'Outcome proyek belum kuantitatif' (priority: 'Tinggi'), issue 'Penamaan teknologi tidak konsisten' (priority: 'Sedang'), issue 'Deskripsi proyek terlalu panjang dan kurang ringkas' (priority: 'Rendah').

4. Kelengkapan Modul CV (completeness):
   - Tentukan persentase kelengkapan tiap bagian CV (0-100):
     - personal_info: Kelengkapan kontak & informasi pribadi (misal: 100)
     - education: Kelengkapan riwayat pendidikan (misal: 100)
     - experience_projects: Kelengkapan proyek & pengalaman (misal: 85)
     - skills: Kelengkapan keahlian teknis (misal: 90)
     - certifications_training: Kelengkapan sertifikasi & pelatihan (misal: 70)

5. Matriks Gap Kompetensi (competency_gaps):
   - Hasilkan 4-6 kompetensi relevan untuk posisi target magang.
   - Format: array objek { skill: string, current_level: 'Dasar' | 'Menengah' | 'Lanjutan', current_level_score: 1-5, target_level: 'Menengah' | 'Lanjutan', target_level_score: 1-5, gap_levels: string (misal: '1 level', '2 level', 'Sesuai Target'), priority: 'Tinggi' | 'Sedang' | 'Rendah' }
   - Contoh skill: 'React / Next.js' (current: Menengah, score: 3, target: Lanjutan, score: 5, gap: '2 level', priority: 'Tinggi')

6. Rekomendasi Modul Belajar (learning_recommendations):
   - Hasilkan 3 rekomendasi modul pembelajaran praktis untuk menutup gap kompetensi.
   - Format: array objek { title: string, description: string, duration: string (misal: '2 minggu', '1 minggu'), icon_type: 'code' | 'github' | 'language' | 'general' }

7. Rekomendasi Lowongan Magang (recommendations):
   - Cocokkan profil kandidat dengan lowongan magang aktif di sistem. Pilihlah lowongan paling relevan.
   - Jika tidak ada lowongan yang aktif cocok, buat rekomendasi umum dan set is_general_recommendation = true.
   - Format: array objek { job_opening_id: string (ID lowongan jika matched, atau null), title: string, company_name: string, location: string, work_type: 'Hybrid' | 'Onsite' | 'Remote', match_score: integer (0-100), matched_skills: array of string, reasoning: string, is_general_recommendation: boolean }

8. Analisis Kredibilitas CV (credibility):
   - Timeline Validation (Bobot 30%, Skor awal 100)
   - Claim Plausibility Check (Bobot 40%, Skor awal 100)
   - Consistency Check (Bobot 30%, Skor awal 100)
   - Tentukan score, level ('HIGH' | 'MEDIUM' | 'LOW'), action ('PROCEED' | 'REVIEW' | 'REJECT'), review_required (boolean), flags[], flags_by_level{}, summary{}.";

            $schema = new Schema(
                type: DataType::OBJECT,
                properties: [
                    'ats_score' => new Schema(type: DataType::INTEGER),
                    'ats_quality' => new Schema(type: DataType::STRING),
                    'competency_match_score' => new Schema(type: DataType::INTEGER),
                    'competency_quality' => new Schema(type: DataType::STRING),
                    'internship_readiness_score' => new Schema(type: DataType::INTEGER),
                    'readiness_quality' => new Schema(type: DataType::STRING),
                    'verification_score' => new Schema(type: DataType::INTEGER),
                    'verification_status' => new Schema(type: DataType::STRING),
                    'verification_note' => new Schema(type: DataType::STRING),

                    'profile_summary' => new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'name' => new Schema(type: DataType::STRING),
                            'initials' => new Schema(type: DataType::STRING),
                            'education' => new Schema(type: DataType::STRING),
                            'institution_years' => new Schema(type: DataType::STRING),
                            'target_role' => new Schema(type: DataType::STRING),
                            'skills' => new Schema(type: DataType::ARRAY, items: new Schema(type: DataType::STRING)),
                            'portfolio_url' => new Schema(type: DataType::STRING),
                            'github_url' => new Schema(type: DataType::STRING),
                            'strengths' => new Schema(type: DataType::ARRAY, items: new Schema(type: DataType::STRING)),
                        ],
                        required: ['name', 'education', 'skills', 'strengths']
                    ),

                    'improvements' => new Schema(
                        type: DataType::ARRAY,
                        items: new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'issue' => new Schema(type: DataType::STRING),
                                'priority' => new Schema(type: DataType::STRING),
                            ],
                            required: ['issue', 'priority']
                        )
                    ),

                    'completeness' => new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'personal_info' => new Schema(type: DataType::INTEGER),
                            'education' => new Schema(type: DataType::INTEGER),
                            'experience_projects' => new Schema(type: DataType::INTEGER),
                            'skills' => new Schema(type: DataType::INTEGER),
                            'certifications_training' => new Schema(type: DataType::INTEGER),
                        ],
                        required: ['personal_info', 'education', 'experience_projects', 'skills', 'certifications_training']
                    ),

                    'competency_gaps' => new Schema(
                        type: DataType::ARRAY,
                        items: new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'skill' => new Schema(type: DataType::STRING),
                                'current_level' => new Schema(type: DataType::STRING),
                                'current_level_score' => new Schema(type: DataType::INTEGER),
                                'target_level' => new Schema(type: DataType::STRING),
                                'target_level_score' => new Schema(type: DataType::INTEGER),
                                'gap_levels' => new Schema(type: DataType::STRING),
                                'priority' => new Schema(type: DataType::STRING),
                            ],
                            required: ['skill', 'current_level', 'current_level_score', 'target_level', 'target_level_score', 'gap_levels', 'priority']
                        )
                    ),

                    'learning_recommendations' => new Schema(
                        type: DataType::ARRAY,
                        items: new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'title' => new Schema(type: DataType::STRING),
                                'description' => new Schema(type: DataType::STRING),
                                'duration' => new Schema(type: DataType::STRING),
                                'icon_type' => new Schema(type: DataType::STRING),
                            ],
                            required: ['title', 'description', 'duration', 'icon_type']
                        )
                    ),

                    'recommendations' => new Schema(
                        type: DataType::ARRAY,
                        items: new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'job_opening_id' => new Schema(type: DataType::STRING),
                                'title' => new Schema(type: DataType::STRING),
                                'company_name' => new Schema(type: DataType::STRING),
                                'location' => new Schema(type: DataType::STRING),
                                'work_type' => new Schema(type: DataType::STRING),
                                'match_score' => new Schema(type: DataType::INTEGER),
                                'matched_skills' => new Schema(type: DataType::ARRAY, items: new Schema(type: DataType::STRING)),
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
                required: ['ats_score', 'competency_match_score', 'internship_readiness_score', 'verification_score', 'profile_summary', 'improvements', 'completeness', 'competency_gaps', 'learning_recommendations', 'recommendations', 'credibility']
            );

            // Fetch PDF path using the default Storage disk path with fallbacks
            $pdfPath = \Illuminate\Support\Facades\Storage::path($analytic->file_path);
            if (!file_exists($pdfPath)) {
                try {
                    $pdfPath = \Illuminate\Support\Facades\Storage::disk('local')->path($analytic->file_path);
                } catch (\Throwable $e) {}
            }
            if (!file_exists($pdfPath)) {
                try {
                    $pdfPath = \Illuminate\Support\Facades\Storage::disk('public')->path($analytic->file_path);
                } catch (\Throwable $e) {}
            }
            if (!file_exists($pdfPath)) {
                $pdfPath = storage_path('app/' . $analytic->file_path);
            }
            if (!file_exists($pdfPath)) {
                $pdfPath = storage_path('app/private/' . $analytic->file_path);
            }
            if (!file_exists($pdfPath)) {
                $pdfPath = storage_path('app/public/' . $analytic->file_path);
            }
            if (!file_exists($pdfPath)) {
                throw new \Exception("File PDF CV tidak ditemukan di path storage mana pun: " . $pdfPath);
            }

            $result = Gemini::generativeModel("gemini-3.1-flash-lite")->withGenerationConfig(
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

            // Ensure profile initials
            if (isset($jsonResponse['profile_summary'])) {
                $profName = $jsonResponse['profile_summary']['name'] ?? '';
                if (empty($jsonResponse['profile_summary']['initials']) && !empty($profName)) {
                    $words = explode(' ', trim($profName));
                    $initials = '';
                    foreach (array_slice($words, 0, 2) as $w) {
                        $initials .= strtoupper(substr($w, 0, 1));
                    }
                    $jsonResponse['profile_summary']['initials'] = $initials ?: 'RS';
                }
            }

            // Ensure quality labels
            if (!isset($jsonResponse['ats_quality'])) {
                $ats = $jsonResponse['ats_score'] ?? 85;
                $jsonResponse['ats_quality'] = $ats >= 85 ? 'Sangat Baik' : ($ats >= 70 ? 'Baik' : ($ats >= 55 ? 'Cukup' : 'Perlu Ditingkatkan'));
            }
            if (!isset($jsonResponse['competency_quality'])) {
                $comp = $jsonResponse['competency_match_score'] ?? 80;
                $jsonResponse['competency_quality'] = $comp >= 85 ? 'Sangat Baik' : ($comp >= 70 ? 'Baik' : ($comp >= 55 ? 'Cukup' : 'Perlu Ditingkatkan'));
            }
            if (!isset($jsonResponse['readiness_quality'])) {
                $ready = $jsonResponse['internship_readiness_score'] ?? 80;
                $jsonResponse['readiness_quality'] = $ready >= 85 ? 'Sangat Baik' : ($ready >= 70 ? 'Baik' : ($ready >= 55 ? 'Cukup' : 'Perlu Ditingkatkan'));
            }
            if (!isset($jsonResponse['verification_status'])) {
                $ver = $jsonResponse['verification_score'] ?? 100;
                $jsonResponse['verification_status'] = $ver >= 90 ? 'Terverifikasi' : ($ver >= 60 ? 'Sebagian Terverifikasi' : 'Perlu Verifikasi');
            }
            if (!isset($jsonResponse['verification_note'])) {
                $jsonResponse['verification_note'] = 'Identitas dan data pendidikan telah diverifikasi.';
            }

            // Sync improvement_suggestions if missing
            if (empty($jsonResponse['improvement_suggestions']) && !empty($jsonResponse['improvements'])) {
                $jsonResponse['improvement_suggestions'] = array_map(function($imp) {
                    return ($imp['issue'] ?? '') . (isset($imp['priority']) ? " [Prioritas: {$imp['priority']}]" : "");
                }, $jsonResponse['improvements']);
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

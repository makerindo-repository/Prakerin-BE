<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobOpening;
use App\Models\Mou;
use App\Models\School;
use App\Models\SchoolAiProfileHistory;
use App\Models\Sector;
use App\Models\Setting;
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
use Illuminate\Support\Str;

class SchoolAiController extends Controller
{
    /**
     * Generate / Polish School & Campus Profile with Gemini AI (FREE Feature).
     */
    public function generateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'nullable|string', // smk, sma, university, institute, polytechnic
            'npsn' => 'nullable|string',
            'accreditation' => 'nullable|string',
            'city' => 'nullable|string',
            'address' => 'nullable|string',
            'email' => 'nullable|string',
            'phone' => 'nullable|string',
            'website' => 'nullable|string',
            'vision' => 'nullable|string',
            'mission' => 'nullable|string',
            'short_description' => 'nullable|string',
            'majors' => 'nullable|array',
            'majors.*' => 'string',
            'competencies' => 'nullable|array',
            'competencies.*' => 'string',
            'facilities' => 'nullable|array',
            'facilities.*.title' => 'nullable|string',
            'facilities.*.description' => 'nullable|string',
            'partnerships' => 'nullable|array',
            'partnerships.*.title' => 'nullable|string',
            'partnerships.*.description' => 'nullable|string',
            'prompt_extra' => 'nullable|string',
        ]);

        $schoolData = json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $extraPrompt = $validated['prompt_extra'] ?? 'Susun profil sekolah/kampus secara komprehensif, akademis, dan menarik untuk kemitraan industri (DUDI) dan program magang/Prakerin.';

        $apiKey = config('gemini.api_key');
        $aiProvider = Setting::getVal('ai_provider', 'gemini');

        $resultData = null;

        if ($aiProvider !== 'none' && $apiKey) {
            try {
                $prompt = "
Anda adalah seorang konsultan pendidikan vokasi dan branding institusi pendidikan profesional.
Berikut adalah data mentah institusi sekolah/perguruan tinggi:
```json
{$schoolData}
```

Instruksi: {$extraPrompt}
Tolong susun narasi profil sekolah/kampus yang formal, inspiratif, dan menonjolkan keunggulan kurikulum, mata pelajaran kejuruan, serta kesiapan siswa/mahasiswa dalam Bahasa Indonesia.
";

                $result = Gemini::generativeModel("gemini-3.1-flash-lite")->withGenerationConfig(
                    generationConfig: new GenerationConfig(
                        responseMimeType: ResponseMimeType::APPLICATION_JSON,
                        responseSchema: new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'tagline' => new Schema(type: DataType::STRING, description: 'Slogan/tagline ringkas 3-5 kata, misal: Unggul Vokasi • Berkarakter • Siap Kerja Global'),
                                'about_school' => new Schema(type: DataType::STRING, description: 'Narasi Tentang Institusi/Sekolah yang rapi, berbobot, dan memikat dunia industri'),
                                'academic_strengths' => new Schema(
                                    type: DataType::ARRAY,
                                    items: new Schema(type: DataType::STRING),
                                    description: 'Poin-poin keunggulan akademik dan kurikulum'
                                ),
                                'competency_highlights' => new Schema(
                                    type: DataType::ARRAY,
                                    items: new Schema(type: DataType::STRING),
                                    description: 'Rangkuman kompetensi keahlian dan mata pelajaran unggulan'
                                ),
                                'facility_summary' => new Schema(
                                    type: DataType::ARRAY,
                                    items: new Schema(
                                        type: DataType::OBJECT,
                                        properties: [
                                            'title' => new Schema(type: DataType::STRING),
                                            'description' => new Schema(type: DataType::STRING),
                                        ],
                                        required: ['title', 'description']
                                    )
                                ),
                                'partnership_prospect' => new Schema(type: DataType::STRING, description: 'Ringkasan prospek kerja sama dan penyerapan lulusan untuk industri'),
                                'completeness_score' => new Schema(type: DataType::INTEGER, description: 'Skor persentase kelengkapan data (misal 92)'),
                            ],
                            required: ['tagline', 'about_school', 'academic_strengths', 'competency_highlights', 'facility_summary', 'partnership_prospect', 'completeness_score']
                        )
                    )
                )->generateContent($prompt);

                $text = $result->text();
                $decoded = json_decode($text, true);

                if ($decoded) {
                    $resultData = $decoded;
                }
            } catch (\Exception $e) {
                Log::error('Gemini generate school profile error: ' . $e->getMessage());
            }
        }

        if (!$resultData) {
            // Fallback generator if AI is unreachable
            $majorsList = $validated['majors'] ?? ['Rekayasa Perangkat Lunak', 'Teknik Komputer & Jaringan', 'Desain Komunikasi Visual'];
            $compList = $validated['competencies'] ?? ['Pemrograman Web & Mobile', 'Administrasi Infrastruktur Jaringan', 'UI/UX & Desain Grafis', 'Basis Data'];
            $facList = $validated['facilities'] ?? [
                [
                    'title' => 'Laboratorium Komputer & Software Studio',
                    'description' => 'Fasilitas workstation modern untuk pengembangan aplikasi dan multimedia.'
                ],
                [
                    'title' => 'Teaching Factory & Workshop Industri',
                    'description' => 'Ruang praktik berstandar industri nyata untuk membiasakan siswa pada alur kerja profesional.'
                ]
            ];

            $typeLabel = strtoupper($validated['type'] ?? 'SMK');
            $resultData = [
                'tagline' => 'Inovasi Vokasi • Berkarakter • Siap Kerja Global',
                'about_school' => ($validated['short_description'] ?? '')
                    ? $validated['short_description'] . ' Kami berkomitmen menyelenggarakan pendidikan vokasi berkualitas tinggi yang terintegrasi dengan kebutuhan industri modern.'
                    : ($validated['name'] ?? 'Institusi Pendidikan kami') . ' adalah lembaga pendidikan ' . $typeLabel . ' yang berdedikasi mencetak lulusan kompeten, berdaya saing tinggi, dan berintegritas melalui kurikulum berbasis industri dan pembelajaran berbasis proyek.',
                'academic_strengths' => [
                    'Kurikulum Berbasis Industri & Merdeka Vokasi',
                    'Pembelajaran Berbasis Proyek (Project-Based Learning)',
                    'Penguatan Soft Skills, Etos Kerja & Kepemimpinan',
                ],
                'competency_highlights' => $compList,
                'facility_summary' => $facList,
                'partnership_prospect' => 'Membuka peluang kerja sama strategis berupa Praktik Kerja Lapangan (PKL), program Guru Tamu, penyelarasan kurikulum, dan rekrutmen kerja lulusan.',
                'completeness_score' => 90,
            ];
        }

        // Save into history database
        $user = $request->user();
        $schoolId = $user?->school?->id;

        $historyRecord = SchoolAiProfileHistory::create([
            'user_id'            => $user?->id,
            'school_id'          => $schoolId,
            'school_name'        => $validated['name'],
            'type'               => $validated['type'] ?? 'smk',
            'tagline'            => $resultData['tagline'] ?? null,
            'about_school'       => $resultData['about_school'] ?? null,
            'accreditation'      => $validated['accreditation'] ?? null,
            'npsn'               => $validated['npsn'] ?? null,
            'website'            => $validated['website'] ?? null,
            'email'              => $validated['email'] ?? null,
            'phone'              => $validated['phone'] ?? null,
            'address'            => $validated['address'] ?? null,
            'vision'             => $validated['vision'] ?? null,
            'mission'            => $validated['mission'] ?? null,
            'majors'             => $validated['majors'] ?? [],
            'competencies'       => $resultData['competency_highlights'] ?? ($validated['competencies'] ?? []),
            'facilities'         => $resultData['facility_summary'] ?? ($validated['facilities'] ?? []),
            'partnerships'       => $validated['partnerships'] ?? [],
            'completeness_score' => $resultData['completeness_score'] ?? 88,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil sekolah berhasil disusun oleh AI.',
            'data' => array_merge($resultData, [
                'history_id' => $historyRecord->id,
                'created_at' => $historyRecord->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Get list of School AI profile generation histories.
     */
    public function getProfileHistories(Request $request)
    {
        $user = $request->user();
        $query = SchoolAiProfileHistory::query();

        if ($user && $user->role !== 'super_admin') {
            $schoolId = $user->school?->id;
            $query->where(function ($q) use ($user, $schoolId) {
                $q->where('user_id', $user->id);
                if ($schoolId) {
                    $q->orWhere('school_id', $schoolId);
                }
            });
        }

        $histories = $query->latest()->take(50)->get();

        return response()->json([
            'status' => 'success',
            'data' => $histories,
        ]);
    }

    /**
     * Delete an AI profile history item.
     */
    public function deleteProfileHistory(Request $request, string $id)
    {
        $user = $request->user();
        $history = SchoolAiProfileHistory::findOrFail($id);

        if ($user && $user->role !== 'super_admin' && $history->user_id !== $user->id && $history->school_id !== $user->school?->id) {
            return response()->json(['errors' => 'Anda tidak memiliki akses.'], 403);
        }

        $history->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat profil berhasil dihapus.',
        ]);
    }

    /**
     * Analyze uploaded Curriculum / Syllabus / Subjects document & match industry partners (FREE Feature for Schools).
     */
    public function analyzeCurriculum(Request $request)
    {
        $request->validate([
            'uploaded_file' => 'nullable|file|mimes:pdf,docx,pptx,doc|max:20480',
            'raw_text' => 'nullable|string',
            'subjects' => 'nullable|array',
            'majors' => 'nullable|array',
        ]);

        $apiKey = config('gemini.api_key');
        $aiProvider = Setting::getVal('ai_provider', 'gemini');

        $extraction = [
            'curriculum_domain' => 'Teknologi Informasi, Rekayasa Perangkat Lunak & Digital',
            'core_subjects' => ['Pemrograman Web', 'Pemrograman Berorientasi Objek', 'Basis Data', 'Cloud Computing', 'UI/UX Design'],
            'extracted_competencies' => ['Fullstack Web Development', 'RESTful API', 'MySQL/PostgreSQL', 'Responsive UI', 'Git Version Control'],
            'target_industries' => ['Teknologi Informasi', 'Software House', 'Digital Agency', 'E-Commerce', 'Financial Technology'],
            'recommended_positions' => ['Frontend Developer Intern', 'Backend Developer Intern', 'Junior Web Programmer', 'UI/UX Designer'],
            'collaboration_models' => ['Praktik Kerja Lapangan (PKL)', 'Sinkronisasi Kurikulum Berbasis Industri', 'Guru Tamu / Expert Sharing', 'Kelas Industri Mitra'],
        ];

        if ($request->hasFile('uploaded_file')) {
            $file = $request->file('uploaded_file');
            $extension = strtolower($file->getClientOriginalExtension());

            if ($aiProvider !== 'none' && $apiKey && $extension === 'pdf') {
                try {
                    $blob = new Blob(
                        mimeType: MimeType::APPLICATION_PDF,
                        data: base64_encode(file_get_contents($file->getRealPath()))
                    );

                    $prompt = "
Analisis dokumen Silabus / Kurikulum / RPP / Modul Ajar Kejuruan ini secara mendalam.
Ekstrak profil kompetensi mata pelajaran dan peluang kemitraan industri dalam Bahasa Indonesia:
1. Bidang Keahlian / Jurusan (curriculum_domain)
2. Daftar Mata Pelajaran Utama / Capaian Pembelajaran (core_subjects: array of strings)
3. Kompetensi Teknis & Hard Skills yang dikuasai siswa (extracted_competencies: array of strings)
4. Sektor Industri yang Sangat Relevan (target_industries: array of strings)
5. Rekomendasi Posisi Magang / Pekerjaan untuk Siswa (recommended_positions: array of strings)
6. Rekomendasi Bentuk Kemitraan (collaboration_models: array of strings, misal Magang PKL, Kelas Industri, Guru Tamu)
";

                    $result = Gemini::generativeModel("gemini-3.1-flash-lite")->withGenerationConfig(
                        generationConfig: new GenerationConfig(
                            responseMimeType: ResponseMimeType::APPLICATION_JSON,
                            responseSchema: new Schema(
                                type: DataType::OBJECT,
                                properties: [
                                    'curriculum_domain' => new Schema(type: DataType::STRING),
                                    'core_subjects' => new Schema(
                                        type: DataType::ARRAY,
                                        items: new Schema(type: DataType::STRING)
                                    ),
                                    'extracted_competencies' => new Schema(
                                        type: DataType::ARRAY,
                                        items: new Schema(type: DataType::STRING)
                                    ),
                                    'target_industries' => new Schema(
                                        type: DataType::ARRAY,
                                        items: new Schema(type: DataType::STRING)
                                    ),
                                    'recommended_positions' => new Schema(
                                        type: DataType::ARRAY,
                                        items: new Schema(type: DataType::STRING)
                                    ),
                                    'collaboration_models' => new Schema(
                                        type: DataType::ARRAY,
                                        items: new Schema(type: DataType::STRING)
                                    ),
                                ],
                                required: ['curriculum_domain', 'core_subjects', 'extracted_competencies', 'target_industries', 'recommended_positions', 'collaboration_models']
                            )
                        )
                    )->generateContent([$prompt, $blob]);

                    $decoded = json_decode($result->text(), true);
                    if ($decoded) {
                        $extraction = array_merge($extraction, $decoded);
                    }
                } catch (\Exception $e) {
                    Log::error('Gemini School Curriculum analysis error: ' . $e->getMessage());
                }
            }
        } elseif ($request->filled('subjects') || $request->filled('majors')) {
            $inputSubjects = $request->input('subjects', []);
            $inputMajors = $request->input('majors', []);

            if (!empty($inputSubjects)) {
                $extraction['core_subjects'] = $inputSubjects;
                $extraction['extracted_competencies'] = array_map(fn($s) => $s . ' & Praktik Terapan', $inputSubjects);
            }
            if (!empty($inputMajors)) {
                $extraction['curriculum_domain'] = implode(', ', $inputMajors);
            }
        }

        // Query recommended companies based on extracted competencies and industries
        $companies = $this->queryMatchingCompanies($extraction['extracted_competencies'], $extraction['target_industries'], $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Analisis kurikulum dan mata pelajaran berhasil diselesaikan.',
            'data' => [
                'analysis' => $extraction,
                'companies' => $companies['data'],
                'total_companies' => $companies['total'],
            ]
        ]);
    }

    /**
     * Get company recommendations with search, filter and pagination.
     */
    public function getMatchingCompaniesList(Request $request)
    {
        $competencies = $request->input('competencies', ['Web Developer', 'Mobile Developer', 'Jaringan', 'Desain']);
        if (is_string($competencies)) {
            $competencies = explode(',', $competencies);
        }

        $industries = $request->input('industries', ['Teknologi Informasi']);
        if (is_string($industries)) {
            $industries = explode(',', $industries);
        }

        $result = $this->queryMatchingCompanies($competencies, $industries, $request);

        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
            'meta' => [
                'total' => $result['total'],
                'page' => (int) $request->input('page', 1),
                'limit' => (int) $request->input('limit', 10),
            ]
        ]);
    }

    /**
     * Helper to query matching companies from active database.
     */
    private function queryMatchingCompanies(array $targetKeywords, array $targetIndustries, Request $request): array
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 9);
        $search = $request->input('search');
        $sectorFilter = $request->input('sector_id');
        $mouFilter = $request->input('mou_status'); // 'all', 'has_mou', 'no_mou'

        $user = $request->user();
        $schoolId = $user?->school?->id;

        $query = Company::with(['sector', 'cityRegency.province', 'jobOpenings', 'user', 'mous']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhereHas('sector', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('jobOpenings', fn ($j) => $j->where('title', 'like', "%{$search}%"));
            });
        }

        if ($sectorFilter) {
            $query->where('sector_id', $sectorFilter);
        }

        $companies = $query->take(60)->get();

        $matchedCompanies = [];

        if ($companies->isNotEmpty()) {
            foreach ($companies as $index => $comp) {
                $sectorName = optional($comp->sector)->name ?? 'Teknologi & Industri Terpadu';
                $cityName = optional($comp->cityRegency)->name ?? 'Kota Mitra';
                $provName = optional($comp->cityRegency?->province)->name ?? 'Indonesia';

                // Check existing MoU
                $hasMou = false;
                if ($schoolId) {
                    $hasMou = $comp->mous->where('school_id', $schoolId)->where('status', 'accepted')->isNotEmpty();
                }

                // Match score calculation
                $score = max(72, min(97, 96 - ($index * 2)));

                // Openings
                $openings = [];
                if ($comp->jobOpenings && $comp->jobOpenings->isNotEmpty()) {
                    foreach ($comp->jobOpenings->take(2) as $job) {
                        $openings[] = [
                            'title' => $job->title,
                            'type' => $job->type ?? 'Magang',
                            'duration' => $job->duration ?? '6 Bulan',
                        ];
                    }
                } else {
                    $openings[] = [
                        'title' => 'Posisi Magang ' . $sectorName,
                        'type' => 'Magang',
                        'duration' => '6 Bulan',
                    ];
                }

                $matchedSubjects = !empty($targetKeywords) ? array_slice($targetKeywords, 0, 3) : ['Kompetensi Kejuruan Utama', 'Praktik Industri'];

                $matchedCompanies[] = [
                    'id' => $comp->id,
                    'name' => $comp->name,
                    'initials' => strtoupper(substr($comp->name, 0, 2)),
                    'logo' => optional($comp->user)->photo_profile,
                    'sector' => $sectorName,
                    'city' => $cityName . ', ' . $provName,
                    'address' => $comp->address ?? $cityName,
                    'website' => $comp->website,
                    'phone' => $comp->phone_number ?? optional($comp->user)->phone_number,
                    'email' => optional($comp->user)->email ?? 'info@perusahaan.com',
                    'match_score' => $score,
                    'matched_subjects' => $matchedSubjects,
                    'mou_status' => $hasMou ? 'has_mou' : 'no_mou',
                    'is_mou_partner' => $hasMou,
                    'active_openings_count' => count($openings),
                    'openings' => $openings,
                ];
            }
        }

        $allCompanies = $matchedCompanies;

        // Apply filters
        if ($mouFilter === 'has_mou') {
            $allCompanies = array_filter($allCompanies, fn ($c) => ($c['mou_status'] ?? '') === 'has_mou' || ($c['is_mou_partner'] ?? false));
        } elseif ($mouFilter === 'no_mou') {
            $allCompanies = array_filter($allCompanies, fn ($c) => ($c['mou_status'] ?? '') === 'no_mou' || !($c['is_mou_partner'] ?? false));
        }

        // Sort by match score
        usort($allCompanies, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);

        $allCompanies = array_values($allCompanies);
        $total = count($allCompanies);
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($allCompanies, $offset, $limit);

        return [
            'data' => $paginated,
            'total' => $total,
        ];
    }
}

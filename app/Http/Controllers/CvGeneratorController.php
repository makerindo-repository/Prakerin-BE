<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\GeneratedCv;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Gemini\Enums\ResponseMimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CvGeneratorController extends Controller
{

    #[OA\Post(
        path: '/cv-generator',
        summary: 'Generate Curriculum Vitae menggunakan AI Gemini',
        description: 'Menghasilkan ringkasan CV profesional berdasarkan data profil pengguna menggunakan Google Gemini AI.',
        tags: ['CV Generator']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['profile_user'],
            properties: [
                new OA\Property(
                    property: 'profile_user',
                    type: 'object',
                    description: 'Data profil pengguna'
                ),
                new OA\Property(
                    property: 'prompt_user',
                    type: 'string',
                    nullable: true,
                    description: 'Instruksi tambahan untuk AI'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'CV berhasil dibuat oleh AI'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation Error'
    )]
    #[OA\Response(
        response: 502,
        description: 'Terjadi kesalahan saat menghubungi layanan Gemini AI'
    )]
    public function generate(Request $request)
    {
        // Increase maximum execution time for AI processing
        @set_time_limit(120);

        $validated = $request->validate([
            'profile_user' => 'required|array',
            'prompt_user' => 'nullable|string'
        ]);

        // Check AI Provider and Gemini API key
        $aiProvider = \App\Models\Setting::getVal('ai_provider', 'gemini');
        if ($aiProvider === 'none') {
            return response()->json([
                'message' => 'Layanan pembuatan CV berbasis AI dinonaktifkan oleh administrator.'
            ], 403);
        }

        if (!config('gemini.api_key')) {
            return response()->json([
                'message' => 'Layanan Pembuatan CV belum siap. Kunci API Gemini belum dikonfigurasi di menu Pengaturan Sistem.'
            ], 500);
        }

        $profile = json_encode($validated['profile_user'], JSON_PRETTY_PRINT);
        $userPrompt = $validated['prompt_user'] ?? 'Buatkan rinkasan profile dan deskripsi pengalaman yang menarik.';

        // NOTE: removed markdown fences and added a strict "ONLY JSON" instruction
        $prompt = "
        Anda adalah seorang ahli HR profesional.
            Berdasarkan data profil pengguna berikut:
            ```json
            $profile
            ```
            Dan permintaan tambahan dari pengguna: '$userPrompt'
            Tolong hasilkan konten CV yang profesional dan menarik sesuai data yang diberikan.
        ";
        try {
            $result = Gemini::generativeModel("gemini-2.0-flash")->withGenerationConfig(
                generationConfig: new GenerationConfig(
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                    responseSchema: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'full_name' => new Schema(type: DataType::STRING),
                            'email' => new Schema(type:DataType::STRING),
                            'phone_number' => new Schema(type:DataType::STRING),
                            'linkedin_url' => new Schema(type:DataType::STRING),
                            'summary' => new Schema(type: DataType::STRING),
                            'work_experience' => new Schema(
                                type: DataType::ARRAY,
                                items: new Schema(
                                    type: DataType::OBJECT,
                                    properties: [
                                        'job_title' => new Schema(type: DataType::STRING),
                                        'company' => new Schema(type: DataType::STRING),
                                        'start_date' => new Schema(type: DataType::STRING),
                                        'end_date' => new Schema(type: DataType::STRING),
                                        'description_points' => new Schema(
                                            type: DataType::ARRAY,
                                            items: new Schema(
                                                type: DataType::STRING,
                                            )
                                        ),
                                    ],
                                    required: ['job_title', 'company', 'start_date', 'end_date', 'description_points']
                                )
                            ),
                            'education' => new Schema(
                                type: DataType::ARRAY,
                                items: new Schema(
                                    type: DataType::OBJECT,
                                    properties: [
                                        'institution' => new Schema(type: DataType::STRING),
                                        'degree' => new Schema(type: DataType::STRING),
                                        'field_of_study' => new Schema(type: DataType::STRING),
                                        'graduation_year' => new Schema(type: DataType::STRING),
                                    ],
                                    required: ['institution', 'degree', 'field_of_study', 'graduation_year']
                                )
                            ),
                            'skills' => new Schema(type: DataType::ARRAY, items: new Schema(type: DataType::STRING))
                        ],
                        required: ['summary', 'work_experience', 'education', 'skills']
                    )
                )
            )->generateContent($prompt);
        } catch (\JsonException $e) {
            Log::error('Gemini JSON decode error (vendor): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Terjadi kesalahan pada respons AI: respons tidak dapat di-decode.'], 500);
        } catch (\Throwable $e) {
            Log::error('Gemini API Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Terjadi kesalahan saat menghubungi layanan AI: ' . $e->getMessage()], 500);
        }
        // $result = Gemini::generativeModel(model: 'gemini-2.0-flash')
        //     ->withGenerationConfig(
        //         generationConfig: new GenerationConfig(
        //             responseMimeType: ResponseMimeType::APPLICATION_JSON,
        //             responseSchema: new Schema(
        //                 type: DataType::ARRAY,
        //                 items: new Schema(
        //                     type: DataType::OBJECT,
        //                     properties: [
        //                         'recipe_name' => new Schema(type: DataType::STRING),
        //                         'cooking_time_in_minutes' => new Schema(type: DataType::INTEGER)
        //                     ],
        //                     required: ['recipe_name', 'cooking_time_in_minutes'],
        //                 )
        //             )
        //         )
        //     )
        //     ->generateContent('List 5 popular cookie recipes with cooking time');

        // $result->json();
        // $jsonResponse = $result->json();

        // 4. Proses dan Simpan Respons
        // Log::info("RawResponseGemini: " . $result->json());


        // Simpan ke database
        // Asumsi user_id didapat dari autentikasi (misal: auth()->id())
        // $savedCv = GeneratedCv::create([
        //     'user_id' => auth()->id(), // Ganti dengan user ID yang sebenarnya
        //     'generated_content' => $jsonResponse,
        //     'source_prompt' => $prompt
        // ]);

        // 5. Kembalikan data JSON ke frontend
        return response()->json($result->json());
    }
}

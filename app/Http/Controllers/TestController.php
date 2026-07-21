<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Test;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/test',
    summary: 'Get test list',
    tags: ['Test'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Search by test title',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: 'Filter by test type',
            schema: new OA\Schema(
                type: 'string',
                enum: ['theory', 'practice', 'other']
            )
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Pagination limit',
            schema: new OA\Schema(type: 'integer', default: 10)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Test list retrieved successfully'
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthorized'
        )
    ]
)]
    public function index(Request $request)
    {
        $search = $request->query('search', '');
        $limit = $request->query('limit', 10);
        $type = $request->query('type', null);

        $tests = Test::where('company_id', $request->user()->company->id)
            ->where('title', 'like', "%$search%")
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        return response()->json($tests, 200);

    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/test',
    summary: 'Create new test',
    tags: ['Test'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'title',
                'link',
                'description',
                'type'
            ],
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(
                    property: 'link',
                    type: 'string',
                    format: 'uri'
                ),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    enum: ['theory', 'practice', 'other']
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Test created successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        )
    ]
)]
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'link' => 'required|string|active_url|max:255',
            'description' => 'required|string',
            'type' => 'required|in:theory,practice,other',
        ]);

        if ($validated->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validated->errors(),
            ], 400));
        }

        $data = $validated->validated();

        $test = new Test();
        $test->company_id = $request->user()->company->id;
        $test->title = $data['title'];
        $test->link = $data['link'];
        $test->description = $data['description'];
        $test->type = $data['type'];
        $test->save();

        return response()->json([
            'data' => true,
        ], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/test/{id}',
    summary: 'Update test',
    tags: ['Test'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(
                    property: 'link',
                    type: 'string',
                    format: 'uri'
                ),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    enum: ['theory', 'practice', 'other']
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Test updated successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        ),
        new OA\Response(
            response: 404,
            description: 'Test not found'
        )
    ]
)]
    public function update(Request $request, string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Tes tidak ditemukan.',
            ], 404));
        }

        if ($test->company_id !== $request->user()->company->id) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Anda tidak memiliki izin untuk mengubah tes ini.',
            ], 403));
        }

        $validated = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'link' => 'sometimes|required|string|active_url|max:255',
            'description' => 'sometimes|required|string',
            'type' => 'sometimes|required|in:theory,practice,other',
        ]);

        if ($validated->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validated->errors(),
            ], 400));
        }

        $data = $validated->validated();

        foreach (['title', 'link', 'description', 'type'] as $field) {
            if (isset($data[$field])) {
                $test->$field = $data[$field];
            }
        }
        $test->save();

        return response()->json([
            'data' => true,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/test/{id}',
    summary: 'Delete test',
    tags: ['Test'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Test deleted successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        ),
        new OA\Response(
            response: 404,
            description: 'Test not found'
        )
    ]
)]
    public function destroy(string $id, Request $request)
    {
        $test = Test::find($id);

        if (!$test) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Tes tidak ditemukan',
            ], 404));
        }

        if ($test->company_id !== $request->user()->company->id) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Anda tidak memiliki izin untuk menghapus tes ini',
            ], 403));
        }

        $test->delete();

        return response()->json([
            'data' => true,
        ], 200);
    }

    /**
     * Generate test scenario using AI.
     */
    public function generateScenario(Request $request)
    {
        // Increase maximum execution time for AI processing
        @set_time_limit(120);

        $request->validate([
            'job_title' => 'required|string|max:255',
            'skills' => 'nullable|string|max:500',
            'type' => 'required|in:theory,practice,other'
        ]);

        $jobTitle = $request->input('job_title');
        $skills = $request->input('skills', 'Keahlian umum terkait posisi');
        $type = $request->input('type');

        $aiProvider = \App\Models\Setting::getVal('ai_provider', 'gemini');
        if ($aiProvider === 'none') {
            return response()->json(['message' => 'Layanan AI Generator dinonaktifkan oleh administrator.'], 403);
        }

        if (!config('gemini.api_key')) {
            return response()->json([
                'error_type' => 'missing_api_key',
                'message' => 'Layanan AI belum siap. Kunci API Gemini belum dikonfigurasi di menu Pengaturan Sistem.'
            ], 500);
        }

        $typeLabel = $type === 'theory' ? 'teori (pilihan ganda/essay)' : ($type === 'practice' ? 'praktik/koding' : 'lainnya');

        $prompt = "Anda adalah instruktur/penguji profesional.
Tugas Anda adalah merancang sebuah skenario tes seleksi magang untuk posisi: '$jobTitle'.
Keahlian/Topik yang diuji: '$skills'.
Tipe tes yang diinginkan: '$typeLabel'.

Hasilkan skenario tes yang profesional dalam bahasa Indonesia dengan struktur JSON sebagai berikut:
{
  \"title\": \"(Judul tes yang menarik dan ringkas)\",
  \"description\": \"(Deskripsi instruksi pengerjaan tes secara mendetail, kriteria penilaian, dan langkah-langkah penyelesaian)\"
}";

        try {
            $result = \Gemini\Laravel\Facades\Gemini::generativeModel("gemini-3.1-flash-lite")->withGenerationConfig(
                generationConfig: new \Gemini\Data\GenerationConfig(
                    responseMimeType: \Gemini\Enums\ResponseMimeType::APPLICATION_JSON,
                    responseSchema: new \Gemini\Data\Schema(
                        type: \Gemini\Enums\DataType::OBJECT,
                        properties: [
                            'title' => new \Gemini\Data\Schema(type: \Gemini\Enums\DataType::STRING),
                            'description' => new \Gemini\Data\Schema(type: \Gemini\Enums\DataType::STRING),
                        ],
                        required: ['title', 'description']
                    )
                )
            )->generateContent($prompt);

            return response()->json([
                'success' => true,
                'data' => $result->json()
            ]);
        } catch (\Throwable $e) {
            \Log::error('AI Test Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat skenario tes berbasis AI: ' . $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\CurriculumVitae\CurriculumVitaeCreateRequest;
use App\Models\CurriculumVitae;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CurriculumVitaeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/curriculum-vitaes',
        summary: 'Menampilkan daftar Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil daftar Curriculum Vitae'
    )]
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search', '');
        $curriculumVitaes = CurriculumVitae::where('name', 'like', "%$search%")
            ->where('student_id', $request->user()->student->id)
            ->paginate($limit);

        return response()->json($curriculumVitaes);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/curriculum-vitaes',
        summary: 'Menambahkan Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['name', 'file'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Curriculum Vitae berhasil dibuat'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation Error'
    )]
    public function store(CurriculumVitaeCreateRequest $request)
    {
        $data = $request->validated();

        $curriculumVitae = new CurriculumVitae();
        $curriculumVitae->name = $data['name'];

        $filename = now()->format('Ymd_His') . '.' . $request->file('file')->getClientOriginalExtension();
        $curriculumVitae->file = $filename;
        $request->file('file')->storeAs('curriculum-vitaes', $filename);
        $curriculumVitae->student_id = $request->user()->student->id;
        $curriculumVitae->save();

        return response()->json([
            'data' => $curriculumVitae
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/curriculum-vitaes/{id}',
        summary: 'Menampilkan detail Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil detail Curriculum Vitae'
    )]
    #[OA\Response(
        response: 401,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Curriculum Vitae tidak ditemukan'
    )]
    public function show(string $id)
    {
        $curriculumVitae = CurriculumVitae::find($id);

        if (!$curriculumVitae) {
             throw new HttpResponseException(response([
                "errors" => "Curriculum Vitae not found."
            ], 404));
        }

        if ($curriculumVitae->student_id !== request()->user()->student->id) {
             throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 401));
        }

        return response()->json(
            ['data' => $curriculumVitae],
            200
        );
    }


    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/curriculum-vitaes/{id}',
        summary: 'Mengubah Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Curriculum Vitae berhasil diubah'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Curriculum Vitae tidak ditemukan'
    )]
    public function update(Request $request, string $id)
    {
        $curriculumVitae = CurriculumVitae::find($id);

        if (!$curriculumVitae) {
            throw new HttpResponseException(response([
                "errors" => "Curriculum Vitae not found."
            ], 404));
        }

        if ($curriculumVitae->student_id !== $request->user()->student->id) {
            throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 403));
        }

        $validator = Validator::make(array_merge($request->all(), $request->allFiles()), [
            'name' => 'sometimes|required|string|max:255',
            'file' => 'sometimes|nullable|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                "errors" => $validator->errors()
            ], 422));
        }

        $data = $validator->validated();

        $curriculumVitae->name = $data['name'] ?? $curriculumVitae->name;

        if ($request->hasFile('file')) {
            $oldFile = $curriculumVitae->file;
            if ($oldFile) {
                if (Storage::exists("curriculum-vitaes/$oldFile")) {
                    Storage::delete("curriculum-vitaes/$oldFile");
                } elseif (Storage::exists("/curriculum-vitaes/$oldFile")) {
                    Storage::delete("/curriculum-vitaes/$oldFile");
                }
            }

            $file = $request->file('file');
            $filename = now()->format('Ymd_His') . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $curriculumVitae->file = $filename;
            $file->storeAs('curriculum-vitaes', $filename);
        }

        $curriculumVitae->save();

        return response()->json([
            'data' => $curriculumVitae
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/curriculum-vitaes/{id}',
        summary: 'Menghapus Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Curriculum Vitae berhasil dihapus'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden'
    )]
    #[OA\Response(
        response: 404,
        description: 'Curriculum Vitae tidak ditemukan'
    )]
    public function destroy(string $id)
    {
        $curriculumVitae = CurriculumVitae::find($id);

        if (!$curriculumVitae) {
            throw new HttpResponseException(response([
                "errors" => "Curriculum Vitae not found."
            ], 404));
        }

        if ($curriculumVitae->student_id !== request()->user()->student->id) {
            throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 403));
        }

        // Hapus file lama kalau ada
        if (Storage::exists("curriculum-vitaes/$curriculumVitae->file")) {
            Storage::delete("curriculum-vitaes/$curriculumVitae->file");
        } elseif (Storage::exists("/curriculum-vitaes/$curriculumVitae->file")) {
            Storage::delete("/curriculum-vitaes/$curriculumVitae->file");
        }

        // Hapus record dari database
        $curriculumVitae->delete();

        return response()->json([
            'message' => 'Curriculum Vitae and file deleted successfully.'
        ], 200);

    }


    #[OA\Get(
        path: '/curriculum-vitaes/{id}/preview',
        summary: 'Preview file Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Preview PDF berhasil'
    )]
    #[OA\Response(
        response: 404,
        description: 'File atau Curriculum Vitae tidak ditemukan'
    )]
    public function preview(Request $request, string $id)
    {
        $cv = CurriculumVitae::with(['student.user', 'student.school', 'student.major'])->find($id);

        if (!$cv) {
            return response()->json([
                'errors' => 'Curriculum Vitae not found.'
            ], 404);
        }

        $relPath = "curriculum-vitaes/$cv->file";
        $altPath = "/curriculum-vitaes/$cv->file";

        // If file doesn't exist on disk or is an old 1-line placeholder, regenerate it
        $needsRegen = false;
        if (!Storage::exists($relPath) && !Storage::exists($altPath)) {
            $needsRegen = true;
        } else {
            $existingPath = Storage::exists($relPath) ? $relPath : $altPath;
            if (Storage::size($existingPath) < 1000) {
                $needsRegen = true;
            }
        }

        if ($needsRegen) {
            $pdfContent = $this->generateCvPdf($cv);
            Storage::put($relPath, $pdfContent);
        }

        $targetPath = Storage::exists($relPath) ? $relPath : $altPath;
        $path = Storage::path($targetPath);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }


    #[OA\Get(
        path: '/curriculum-vitaes/{id}/download',
        summary: 'Download file Curriculum Vitae',
        tags: ['Curriculum Vitae']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'File berhasil didownload'
    )]
    #[OA\Response(
        response: 404,
        description: 'File atau Curriculum Vitae tidak ditemukan'
    )]
    public function download(Request $request, string $id)
    {
        $cv = CurriculumVitae::with(['student.user', 'student.school', 'student.major'])->find($id);

        if (!$cv) {
            return response()->json([
                'errors' => 'Curriculum Vitae not found.'
            ], 404);
        }

        $relPath = "curriculum-vitaes/$cv->file";
        $altPath = "/curriculum-vitaes/$cv->file";

        // If file doesn't exist on disk or is an old 1-line placeholder, regenerate it
        $needsRegen = false;
        if (!Storage::exists($relPath) && !Storage::exists($altPath)) {
            $needsRegen = true;
        } else {
            $existingPath = Storage::exists($relPath) ? $relPath : $altPath;
            if (Storage::size($existingPath) < 1000) {
                $needsRegen = true;
            }
        }

        if ($needsRegen) {
            $pdfContent = $this->generateCvPdf($cv);
            Storage::put($relPath, $pdfContent);
        }

        $targetPath = Storage::exists($relPath) ? $relPath : $altPath;
        $path = Storage::path($targetPath);

        return response()->download($path, 'cv_' . now()->format('Ymd_His') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function generateCvPdf(CurriculumVitae $cv): string
    {
        $student = $cv->student;
        $user = $student?->user;
        $school = $student?->school;
        $major = $student?->major;

        $name = htmlspecialchars($student?->name ?? $user?->username ?? 'Peserta Magang');
        $email = htmlspecialchars($user?->email ?? '-');
        $phone = htmlspecialchars($student?->phone_number ?? $user?->whatsapp_number ?? '-');
        $schoolName = htmlspecialchars($school?->name ?? 'Sekolah / Universitas');
        $majorName = htmlspecialchars($major?->name ?? '-');
        $className = htmlspecialchars($student?->class ?? '-');
        $skills = htmlspecialchars($student?->skill ?? 'Komunikasi, Kerja Sama Tim');
        $address = htmlspecialchars($student?->address ?? '-');
        $portfolio = htmlspecialchars($student?->portofolio_link ?? '-');
        $social = htmlspecialchars($student?->social_media_link ?? '-');

        $skillsHtml = '';
        foreach (explode(',', $skills) as $s) {
            $trimmed = trim($s);
            if ($trimmed) {
                $skillsHtml .= "<span class='skill-badge'>" . htmlspecialchars($trimmed) . "</span> ";
            }
        }

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Curriculum Vitae - {$name}</title>
            <style>
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    color: #333333;
                    margin: 0;
                    padding: 30px;
                    line-height: 1.5;
                }
                .header {
                    border-bottom: 2px solid #00809d;
                    padding-bottom: 18px;
                    margin-bottom: 20px;
                }
                .name {
                    font-size: 24px;
                    font-weight: bold;
                    color: #00809d;
                    margin: 0 0 4px 0;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .title {
                    font-size: 13px;
                    color: #666666;
                    font-weight: 600;
                    margin-bottom: 10px;
                }
                .contact-table {
                    width: 100%;
                    font-size: 12px;
                    color: #444444;
                }
                .contact-table td {
                    padding: 3px 0;
                }
                .section-title {
                    font-size: 14px;
                    font-weight: bold;
                    color: #00809d;
                    text-transform: uppercase;
                    border-bottom: 1px solid #e0e0e0;
                    padding-bottom: 4px;
                    margin-top: 18px;
                    margin-bottom: 10px;
                }
                .content-box {
                    margin-bottom: 12px;
                }
                .item-title {
                    font-size: 13px;
                    font-weight: bold;
                    color: #222222;
                }
                .item-subtitle {
                    font-size: 12px;
                    color: #555555;
                    font-style: italic;
                }
                .item-desc {
                    font-size: 12px;
                    color: #444444;
                    margin-top: 4px;
                }
                .skill-badge {
                    display: inline-block;
                    background-color: #f0fdfa;
                    border: 1px solid #ccfbf1;
                    color: #0f766e;
                    padding: 4px 10px;
                    border-radius: 10px;
                    font-size: 11px;
                    font-weight: bold;
                    margin-right: 4px;
                    margin-bottom: 4px;
                }
                .footer {
                    margin-top: 35px;
                    border-top: 1px solid #eeeeee;
                    padding-top: 10px;
                    font-size: 10px;
                    color: #888888;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='name'>{$name}</div>
                <div class='title'>Calon Peserta Prakerin / Magang</div>
                <table class='contact-table'>
                    <tr>
                        <td width='50%'><strong>Email:</strong> {$email}</td>
                        <td width='50%'><strong>Telepon / WA:</strong> {$phone}</td>
                    </tr>
                    <tr>
                        <td><strong>Asal Institusi:</strong> {$schoolName}</td>
                        <td><strong>Jurusan / Kelas:</strong> {$majorName} ({$className})</td>
                    </tr>
                    <tr>
                        <td colspan='2'><strong>Alamat:</strong> {$address}</td>
                    </tr>
                </table>
            </div>

            <div class='section-title'>Ringkasan Profil</div>
            <div class='content-box'>
                <p class='item-desc'>
                    Siswa/Mahasiswa berdedikasi dan memiliki motivasi tinggi dari <strong>{$schoolName}</strong> jurusan <strong>{$majorName}</strong>. 
                    Siap berkontribusi aktif, mempelajari alur kerja profesional di industri, serta menerapkan keahlian teknis dan kerja sama tim secara optimal selama program Praktik Kerja Industri (Prakerin) / Magang.
                </p>
            </div>

            <div class='section-title'>Pendidikan</div>
            <div class='content-box'>
                <div class='item-title'>{$schoolName}</div>
                <div class='item-subtitle'>Jurusan {$majorName} &bull; Kelas {$className}</div>
            </div>

            <div class='section-title'>Keahlian & Kompetensi</div>
            <div class='content-box'>
                {$skillsHtml}
            </div>

            " . ($portfolio !== '-' || $social !== '-' ? "
            <div class='section-title'>Portofolio & Media Sosial</div>
            <div class='content-box'>
                <table class='contact-table'>
                    " . ($portfolio !== '-' ? "<tr><td width='25%'><strong>Portofolio:</strong></td><td>{$portfolio}</td></tr>" : "") . "
                    " . ($social !== '-' ? "<tr><td><strong>Profil:</strong></td><td>{$social}</td></tr>" : "") . "
                </table>
            </div>
            " : "") . "

            <div class='footer'>
                Dokumen Curriculum Vitae resmi • Platform PRAKERIN.ID • " . date('d F Y') . "
            </div>
        </body>
        </html>
        ";

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->output();
    }
}

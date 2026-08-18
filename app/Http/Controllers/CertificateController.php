<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Certificate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateController extends Controller
{   
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/certificates',
        summary: 'Menampilkan daftar certificate',
        tags: ['Certificate']
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        description: 'Jumlah data per halaman',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil data certificate'
    )]
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);

        $certificates = Certificate::paginate($limit);

        return response()->json($certificates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    
    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/certificates/{id}',
        summary: 'Menampilkan detail certificate',
        tags: ['Certificate']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil detail certificate'
    )]
    #[OA\Response(
        response: 404,
        description: 'Certificate tidak ditemukan'
    )]
    public function show(string $id)
    {
        $certificate = Certificate::with(
            'internship.internshipApplication.curriculumVitae.student.user',
            'internship.internshipApplication.jobOpening.company.user'
        )->find($id);

        if (!$certificate) {
            throw new HttpResponseException(
                response()->json(['errors' => 'Certificate not found.'], 404)
            );
        }

        return response()->json([
            'data' => $certificate,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    #[OA\Get(
        path: '/certificates/{id}/preview',
        summary: 'Preview PDF Certificate',
        tags: ['Certificate']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'PDF berhasil dibuat'
    )]
    #[OA\Response(
        response: 404,
        description: 'Certificate tidak ditemukan'
    )]
    public function preview(string $id)
    {
        $certificate = Certificate::with(
            'internship.internshipApplication.curriculumVitae.student.user',
            'internship.internshipApplication.jobOpening.company.user'
        )->find($id);

        if (!$certificate) {
            throw new HttpResponseException(
                response()->json(['errors' => 'Certificate not found.'], 404)
            );
        }

        // Buat folder temp jika belum ada
        $tempDir = public_path('temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Generate nama file QR code yang unique
        $qrFileName = 'qr_certificate_' . $certificate->id . '_' . time() . '.png';
        $qrPath = $tempDir . '/' . $qrFileName;

        // Generate QR code dan simpan sebagai file PNG
        QrCode::format('svg')
            ->size(55)
            ->margin(1)
            ->errorCorrection('M')
            ->generate(url("/certificates/{$certificate->id}"), $qrPath);

        // Generate PDF dengan path ke file QR
        $pdf = Pdf::loadView('certificates.template', [
            'certificate' => $certificate,
            'qrPath' => $qrPath,
            'qrUrl' => asset('temp/' . $qrFileName)
        ])->setPaper('a4', 'landscape');

        // Simpan PDF ke memory
        $pdfContent = $pdf->output();

        // Hapus file QR temporary setelah PDF dibuat
        if (file_exists($qrPath)) {
            @unlink($qrPath);
        }

        // Return PDF
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="certificate_' . $certificate->id . '.pdf"');
    }


    #[OA\Get(
        path: '/certificates/{id}/download',
        summary: 'Download Certificate PDF',
        tags: ['Certificate']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'PDF berhasil didownload'
    )]
    #[OA\Response(
        response: 404,
        description: 'Certificate tidak ditemukan'
    )]
    public function download(string $id)
    {
        $certificate = Certificate::with(
            'internship.internshipApplication.curriculumVitae.student.user',
            'internship.internshipApplication.jobOpening.company.user'
        )->find($id);

        if (!$certificate) {
            throw new HttpResponseException(
                response()->json(['errors' => 'Certificate not found.'], 404)
            );
        }

        // Buat folder temp jika belum ada
        $tempDir = public_path('temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Generate nama file QR code yang unique
        $qrFileName = 'qr_certificate_' . $certificate->id . '_' . time() . '.png';
        $qrPath = $tempDir . '/' . $qrFileName;

        // Generate QR code dan simpan sebagai file PNG
        QrCode::format('svg')
            ->size(55)
            ->margin(1)
            ->errorCorrection('M')
            ->generate(url("/certificates/{$certificate->id}"), $qrPath);

        // Generate PDF dengan path ke file QR
        $pdf = Pdf::loadView('certificates.template', [
            'certificate' => $certificate,
            'qrPath' => $qrPath,
            'qrUrl' => asset('temp/' . $qrFileName)
        ])->setPaper('a4', 'landscape');

        // Simpan PDF ke memory
        $pdfContent = $pdf->output();

        // Hapus file QR temporary setelah PDF dibuat
        if (file_exists($qrPath)) {
            @unlink($qrPath);
        }

        // Return PDF
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="certificate_' . $certificate->id . '.pdf"');

    }

    #[OA\Get(
        path: '/certificates/count',
        summary: 'Menghitung jumlah certificate',
        tags: ['Certificate']
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil jumlah certificate'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function count(Request $request)
    {
        
        // $count = Certificate::when($request->user);

        return response()->json([
            "count" => $request->user()->company()
        ], 200);
    }
}

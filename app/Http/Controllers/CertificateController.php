<?php

namespace App\Http\Controllers;

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
            unlink($qrPath);
        }

        // Return PDF
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="certificate_' . $certificate->id . '.pdf"');
    }

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
            unlink($qrPath);
        }

        // Return PDF
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="certificate_' . $certificate->id . '.pdf"');

    }

    public function count(Request $request)
    {
        
        // $count = Certificate::when($request->user);

        return response()->json([
            "count" => $request->user()->company()
        ], 200);
    }
}

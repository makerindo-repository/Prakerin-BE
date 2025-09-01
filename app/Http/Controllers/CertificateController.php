<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

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
        //
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

    }

    public function download(string $id)
    {

        $certificate = Certificate::with(
            'internship.internshipApplication.curriculumVitae.student.user',
            'internship.internshipApplication.jobOpening.company.user'
        )->find($id);

        if (!$certificate) {
            throw new HttpResponseException(response()->json(['errors' => 'Certificate not found.'], 404));
        }

        $pdf = Pdf::loadView('certificates.template', compact('certificate'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("certificate-{$certificate->id}.pdf");

    }
}

<?php

namespace App\Http\Controllers;

// use App\Models\User;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function generateCv(Request $request, PDF $PDF) {
        $user = $request->user();
        // dd($request->work_experience);
        $pdf = $PDF->loadView('CVGenarte.ModernCv', ['data' => $request]);
        $filename = 'cv_'.$user->id.'.pdf';
        $path = storage_path('app/public'.$filename);
        $pdf->save($path);

        return response()->file($path);
    }
}

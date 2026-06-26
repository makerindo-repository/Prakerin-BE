<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
// use App\Models\User;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    /**
     * Generate a PDF of the user's CV.
     */
    #[OA\Post(
    path: '/pdf/generate-cv',
    summary: 'Generate CV PDF',
    tags: ['PDF'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'full_name',
                    type: 'string',
                    example: 'John Doe'
                ),
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    example: 'john@example.com'
                ),
                new OA\Property(
                    property: 'phone',
                    type: 'string',
                    example: '08123456789'
                ),
                new OA\Property(
                    property: 'address',
                    type: 'string'
                ),
                new OA\Property(
                    property: 'summary',
                    type: 'string'
                ),
                new OA\Property(
                    property: 'education',
                    type: 'array',
                    items: new OA\Items(type: 'object')
                ),
                new OA\Property(
                    property: 'work_experience',
                    type: 'array',
                    items: new OA\Items(type: 'object')
                ),
                new OA\Property(
                    property: 'skills',
                    type: 'array',
                    items: new OA\Items(type: 'string')
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'CV PDF generated successfully',
            content: new OA\MediaType(
                mediaType: 'application/pdf'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthorized'
        )
    ]
)]
    public function generateCv(Request $request, PDF $PDF) {
        $user = $request->user();
        // dd($request->work_experience);
        $pdf = $PDF->loadView('CVGenarte.ModernCv', ['data' => $request])->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
        $filename = 'cv_'.$user->id.'.pdf';
        $path = storage_path('app/public'.$filename);
        $pdf->save($path);

        return response()->file($path);
    }
}

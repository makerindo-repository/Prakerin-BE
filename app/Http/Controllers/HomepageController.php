<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\HomePage\HomePageRequest;
use App\Models\CommentPrakerin;
use App\Models\Partner;
use App\Models\JobOpening;
use DB;
use App\Models\Hompage;
use Illuminate\Support\Facades\Auth;
use Log;
class HomepageController extends Controller
{
    #[OA\Get(
    path: '/homepage',
    summary: 'Get homepage data',
    tags: ['Homepage'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Homepage data retrieved successfully'
        )
    ]
)]
    public function index()
    {
        $data = Hompage::where('name', 'LIKE', '%landing%')
            ->orderBy('created_at', 'ASC')
            ->get();
        $partner = Partner::orderBy('created_at', 'ASC')->get();
        $commentPrakerin = CommentPrakerin::orderBy('created_at', 'ASC')->get();
        $jobOpenings = JobOpening::with([
    'company.user',
    'company.cityRegency.province',
    'field',
    'duration',
])
    ->where('is_available', true)
    ->where('closing_date', '>=', now()->toDateString())
    ->orderBy('created_at', 'DESC')
    ->limit(6)
    ->get()
    ->map(function ($item) {
        return [
            "id" => $item->id,
            "title" => $item->title,
            "grade" => $item->grade,
            "type" => $item->type,
            "location" => $item->location,
            "qouta" => $item->qouta,
            "is_paid" => $item->is_paid,
            "is_available" => $item->is_available,
            "start_date" => $item->start_date,
            "closing_date" => $item->closing_date,
            "created_at" => $item->created_at,
            "updated_at" => $item->updated_at,
            "company" => $item->company?->makeHidden(['user', 'cityRegency']),
            "city_regency" => $item->company?->cityRegency?->makeHidden(['province']),
            "province" => $item->company?->cityRegency?->province,
            "user" => $item->company?->user,
            "field" => $item->field,
            "duration" => $item->duration,
        ];
    });

        $formatted = [];
        if ((!Auth::guard('sanctum')->user())) {
            foreach ($data as $item) {
                $formatted[$item->name] = $item->value;
            }
        } else {
            $formatted = $data;
        }


        return response()->json([
            'data' => [
                'homepages' => $formatted,
                'partners' => $partner,
                'comment_prakerins' => $commentPrakerin,
                'job_openings' => $jobOpenings
            ]
        ], 200);
    }


    #[OA\Get(
    path: '/homepage/about',
    summary: 'Get about page data',
    tags: ['Homepage'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'About page retrieved successfully'
        )
    ]
)]
    public function about()
    {
        $data = Hompage::where('name', 'LIKE', '%about%')->get();

        return response()->json([
        'data' => $data
    ], 200);
    }


    #[OA\Put(
    path: '/homepage',
    summary: 'Update homepage content',
    tags: ['Homepage'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'value', type: 'string')
                        ]
                    )
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Homepage updated successfully'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function update(HomePageRequest $request)
    {
        $validated = $request->validated();

        $data = $validated['data'];

        // foreach ($data as $item) {
        //     // Update berdasarkan id
        //     Hompage::where('id', $item['id'])->update([
        //         'name' => $item['name'],
        //         'value' => $item['value'],
        //     ]);
        // }

        $ids = array_column($data, 'id');

        $valueCases = [];
        foreach ($data as $item) {
            $valueCases[] = "WHEN '{$item['id']}' THEN '{$item['value']}'";
        }

        $valueCasesSql = implode(' ', $valueCases);
        $idsSql = "'" . implode("','", $ids) . "'";

        DB::statement("
            UPDATE hompages
            SET value = CASE id
                $valueCasesSql
            END
            WHERE id IN ($idsSql)
        ");

        return response()->json(['data' => true], 200);
    }
}
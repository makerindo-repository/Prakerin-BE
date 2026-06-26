<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Mou;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MouController extends Controller
{
    /**g
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/mou',
    summary: 'Get list of MoU',
    tags: ['MoU'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Search partner name',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: 'Filter status',
            schema: new OA\Schema(
                type: 'string',
                enum: ['pending', 'accepted', 'rejected']
            )
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Pagination limit',
            schema: new OA\Schema(type: 'integer', default: 10)
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'MoU list retrieved successfully'
        )
    ]
)]
    public function index(Request $request)
    {

        $search = $request->query('search', '');
        $type = $request->query('type', null);
        $limit = $request->query('limit', 10);

        $user = $request->user();

        $mous = Mou::query()
            ->when($user->tokenCan('company-access'), function ($query) use ($user, $search) {
                $query->where('company_id', $user->company->id)
                    ->with([
                        'school' => function ($q) {
                            $q->select('id', 'name');
                        }
                    ]);
                $query->whereHas('school', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            })
            ->when($user->tokenCan('school-access'), function ($query) use ($user, $search) {
                $query->where('school_id', $user->school->id)
                    ->with([
                        'company' => function ($q) use ($search) {
                            $q->select('id', 'name');
                        }
                    ]);
                $query->whereHas('company', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            })
            ->when($type && in_array($type, ['pending', 'accepted', 'rejected']), function ($query) use ($type) {
                $query->where('status', $type);
            })
            ->orderBy('updated_at', 'desc')
            ->select('id', 'start_date', 'end_date', 'status', 'file', $user->tokenCan('company-access') ? "school_id" : "company_id")
            ->paginate($limit)
            ->through(function ($item) use ($user) {

                $item->start_date = Carbon::parse($item->start_date)->format('j-n-Y');
                $item->end_date = Carbon::parse($item->end_date)->format('j-n-Y');

                if ($user->tokenCan('company-access')) {
                    unset($item['school_id']);
                } else {
                    unset($item['company_id']);
                }

                return $item;
            });



        return response()->json($mous);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/mou',
    summary: 'Create new MoU',
    tags: ['MoU'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['start_date','end_date','file','message'],
                properties: [
                    new OA\Property(
                        property: 'start_date',
                        type: 'string',
                        format: 'date'
                    ),
                    new OA\Property(
                        property: 'end_date',
                        type: 'string',
                        format: 'date'
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'company_id',
                        type: 'integer',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'school_id',
                        type: 'integer',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'MoU created successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        )
    ]
)]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'file' => 'required|file|mimes:pdf|max:2048',
            'message' => 'required'
        ]);

        if (auth()->user()->tokenCan('school-access')) {
            $validator->addRules([
                'company_id' => 'required|exists:companies,id',
            ]);
        } else if (auth()->user()->tokenCan('company-access')) {
            $validator->addRules([
                'school_id' => 'required|exists:schools,id',
            ]);
        }

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                "errors" => $validator->getMessageBag()
            ], 400));
        }

        $data = $validator->validated();

        $mou = new Mou();


        $mou->start_date = $data['start_date'];
        $mou->end_date = $data['end_date'];
        $mou->message = $data['message'];



        if ($request->file('file')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('file')->getClientOriginalExtension();
            $mou->file = $filename;

            $request->file('file')->storeAs('mous', $filename);
        }

        if (auth()->user()->tokenCan('school-access')) {
            $mou->company_id = $data['company_id'];
            $mou->school_id = auth()->user()?->school->id;
            $mou->is_school_accepted = true;
        } else if (auth()->user()->tokenCan('company-access')) {
            $mou->company_id = auth()->user()?->company->id;
            $mou->school_id = $data['school_id'];
            $mou->is_company_accepted = true;
        }

        $mou->status = 'pending';
        $mou->save();

        return response()->json(['data' => $mou], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: '/mou/{id}',
    summary: 'Get MoU detail',
    tags: ['MoU'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'MoU detail retrieved successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'MoU not found'
        )
    ]
)]
    public function show(Request $request, string $id)
    {
        $mou = Mou::with(['company.user', 'company.cityRegency.province', 'school.user', 'school.cityRegency.province'])
            ->find($id);



        if (!$mou) {
            throw new HttpResponseException(response([
                'errors' => 'Kerja sama tidak ditemukan.'
            ]));
        }


        $user = $request->user();

        $mou = collect([$mou])->map(function ($item) use ($user) {
            $partner = $user->tokenCan('company-access')
                ? $item->school->only(['name', 'website'])
                : $item->company->only(['name', 'website']);


            return [
                'start_date' => $item->start_date,
                'end_date' => $item->end_date,
                'status' => $item->status,
                'file' => $item->file,
                'is_company_accepted' => $item->is_company_accepted,
                'is_school_accepted' => $item->is_school_accepted,
                'reason' => $item->reason,
                'message' => $item->message,
                'user' => $user->tokenCan('company-access') ?
                    $item->company->user->only('email', 'photo_profile') :
                    $item->school->user->only('email', 'photo_profile'),
                'province' => $user->tokenCan('company-access') ?
                    $item->company->cityRegency->province :
                    $item->school->cityRegency->province,
                'city_regency' => $user->tokenCan('company-access')
                    ? $item->company->cityRegency->makeHidden(['province'])
                    : $item->school->cityRegency->makeHidden(['province']),
                'partner' => $partner,
            ];
        })->first();



        return response()->json([
            'data' => $mou
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/mou/{id}',
    summary: 'Accept or Reject MoU',
    tags: ['MoU'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        )
    ],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'reason',
                    type: 'string',
                    nullable: true,
                    example: 'Proposal rejected because requirements are incomplete.'
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'MoU updated successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Unauthorized'
        ),
        new OA\Response(
            response: 404,
            description: 'MoU not found'
        )
    ]
)]
    public function update(Request $request, string $id)
    {
        $mou = Mou::find($id);
        if (!$mou) {
            throw new HttpResponseException(response([
                "errors" => "Mou not found."
            ], 404));
        }

        if ($mou->school_id !== auth()?->user()?->school?->id) {
            if ($mou->company_id !== auth()?->user()?->company?->id) {
                throw new HttpResponseException(response([
                    "errors" => "Unauthorized."
                ], 403));
            }
        }


        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                "errors" => $validator->getMessageBag()
            ], 400));
        }

        $data = $validator->validated();

        if (isset($data['reason'])) {
            $mou->reason = $data['reason'];
            $mou->status = 'rejected';
            if ($request->user()->tokenCan('company-access')) {
                $mou->is_company_accepted = false;
            } else {
                $mou->is_school_accepted = false;
            }
        } else {
            $mou->status = 'accepted';
            if ($request->user()->tokenCan('company-access')) {
                $mou->is_company_accepted = true;
            } else {
                $mou->is_school_accepted = true;
            }
        }

        $mou->save();

        return response()->json(['data' => true], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/mou/{id}',
    summary: 'Delete MoU',
    tags: ['MoU'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'MoU deleted successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Unauthorized'
        ),
        new OA\Response(
            response: 404,
            description: 'MoU not found'
        )
    ]
)]
    public function destroy(string $id)
    {
        $mou = Mou::find($id);
        if (!$mou) {
            throw new HttpResponseException(response([
                "errors" => "Mou not found."
            ], 404));
        }

        if ($mou->school_id !== auth()?->user()?->school?->id) {
            if ($mou->company_id !== auth()?->user()?->company?->id) {
                throw new HttpResponseException(response([
                    "errors" => "Unauthorized."
                ], 403));
            }
        }

        if (Storage::exists('mous/' . $mou->file)) {
            Storage::delete('mous/' . $mou->file);
        }
        $mou->delete();

        return response()->json(['message' => 'Mou deleted successfully'], 200);
    }


    #[OA\Get(
    path: '/mou/{id}/preview',
    summary: 'Preview MoU PDF',
    tags: ['MoU'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'PDF preview'
        ),
        new OA\Response(
            response: 404,
            description: 'File not found'
        )
    ]
)]
    public function preview(Request $request, string $id)
    {
        $mou = Mou::find($id);

        if (!$mou) {
            return response()->json([
                'errors' => 'Kerja sama tidak ditemukan.'
            ], 404);
        }

        // if ($mou->student_id !== $request->user()->student->id) {
        //     return response()->json([
        //         'errors' => 'Forbidden.'
        //     ], 403);
        // }

        if (!Storage::exists("/mous/$mou->file")) {
            return response()->json([
                'errors' => 'File tidak ditemukan.'
            ], 404);
        }
        $path = Storage::path("/mous/$mou->file");

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
        ]);
    }


    #[OA\Get(
    path: '/mou/{id}/download',
    summary: 'Download MoU PDF',
    tags: ['MoU'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'PDF downloaded'
        ),
        new OA\Response(
            response: 404,
            description: 'File not found'
        )
    ]
)]
    public function download(Request $request, string $id)
    {
        $mou = Mou::find($id);

        if (!$mou) {
            return response()->json([
                'errors' => 'Kerja sama tidak ditemukan.'
            ], 404);
        }

        // if ($mou->student_id !== $request->user()->student->id) {
        //     return response()->json([
        //         'errors' => 'Forbidden.'
        //     ], 403);
        // }

        if (!Storage::exists("/mous/$mou->file")) {
            return response()->json([
                'errors' => 'File tidak ditemukan.'
            ], 404);
        }
        $path = Storage::path("/mous/$mou->file");


        return response()->download($path, 'mous_' . now()->format('Ymd_His') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}

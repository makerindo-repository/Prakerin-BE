<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Partner;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Log;

class PartnerController extends Controller
{


    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/partner',
    summary: 'Get partner list',
    tags: ['Partner'],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Search partner by name',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: 'Filter partner type',
            schema: new OA\Schema(
                type: 'string',
                enum: ['school', 'company', 'university']
            )
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Partner list retrieved successfully'
        )
    ]
)]
    public function index(Request $request)
    {
        $search = $request->query('search', '');
        $type = $request->query('type', null);

        $partners = Partner::where('name', 'like', "%{$search}%")
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->orderBy('created_at', 'ASC')
            ->get();

        return response()->json([
            'data' => $partners,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/partner',
    summary: 'Create new partner',
    tags: ['Partner'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['name', 'logo', 'address', 'type'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'SMK Negeri 1 Jakarta'
                    ),
                    new OA\Property(
                        property: 'logo',
                        type: 'string',
                        format: 'binary'
                    ),
                    new OA\Property(
                        property: 'address',
                        type: 'string',
                        example: 'Jl. Sudirman No. 1 Jakarta'
                    ),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['school', 'company', 'university']
                    )
                ]
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Partner created successfully'
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
            'name' => 'required|string|max:255',
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'required|string|max:255',
            'type' => 'required|in:school,company,university'
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                ['errors' => $validator->errors()],
                400
            ));
        }

        $validated = $validator->validated();

        // Ambil file
        $file = $request->file('logo');

        // Tentukan nama baru (misalnya pakai timestamp + original extension)
        $filename = time() . '.' . $file->getClientOriginalExtension();

        // Simpan ke storage/app/public/partner dengan nama baru
        $file->storeAs('partner', $filename, 'public');

        Partner::create([
            'name' => $validated['name'],
            'logo' => $filename,
            'address' => $validated['address'],
            'type' => $validated['type'],
        ]);

        return response()->json(['data' => true], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    #[OA\Post(
    path: '/partner/{id}',
    summary: 'Update partner',
    tags: ['Partner'],
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
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['name', 'address', 'type'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'logo',
                        type: 'string',
                        format: 'binary',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'address',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['school', 'company', 'university']
                    )
                ]
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Partner updated successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        ),
        new OA\Response(
            response: 404,
            description: 'Partner not found'
        )
    ]
)]
    public function update(Request $request, string $id)
    {
        $partner = Partner::find($id);

        if (!$partner) {
            throw new HttpResponseException(response()->json(
                ['errors' => "Mitra tidak ditemukan!"],
                400
            ));
        }


        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'required|string|max:255',
            'type' => 'required|in:school,company,university',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                ['errors' => $validator->errors()],
                400
            ));
        }


        $validated = $validator->validated();

        // Ambil file
        if (isset($validated['logo'])) {
            $file = $request->file('logo');

            // Tentukan nama baru (misalnya pakai timestamp + original extension)
            $filename = time() . '.' . $file->getClientOriginalExtension();

            // Simpan ke storage/app/public/partner dengan nama baru
            $file->storeAs('partner', $filename, 'public');

            if (Storage::disk('public')->exists("partner/{$partner->logo}")) {
                Storage::disk('public')->delete("partner/{$partner->logo}");
            }

            $partner->logo = $filename;
        }


        $partner->name = $validated['name'];
        $partner->address = $validated['address'];
        $partner->type = $validated['type'];
        $partner->save();

        return response()->json(['data' => true], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/partner/{id}',
    summary: 'Delete partner',
    tags: ['Partner'],
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
            description: 'Partner deleted successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Partner not found'
        )
    ]
)]
    public function destroy(string $id)
    {
        $partner = Partner::find($id);

        if (!$partner) {
            throw new HttpResponseException(response()->json(
                ['errors' => "Mitra tidak ditemukan!"],
                400
            ));
        }


        if (Storage::disk('public')->exists("partner/{$partner->logo}")) {
            Storage::disk('public')->delete("partner/{$partner->logo}");
        }

        $partner->delete();



        return response()->json([
            'data' => true
        ], 200);

    }
}

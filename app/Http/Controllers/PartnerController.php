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
        // Default 12 (pas buat grid 3 kolom x 4 baris di /mitra & landing
        // page) — PENTING: sebelum ada pagination di sini, endpoint ini
        // nge-fetch SEMUA partner + query tambahan (Company/School + rating)
        // SATU-SATU per baris. Begitu jumlah partner nembus ribuan (hasil
        // bulk import), itu jadi ribuan query per page-load & bikin
        // /mitra + landing page lemot/timeout. Sekarang dibatasi per
        // halaman, dan query tambahannya di-batch (bukan per-row lagi).
        $perPage = (int) $request->query('limit', 12);
        $page = (int) $request->query('page', 1);

        $query = Partner::where('name', 'like', "%{$search}%")
            ->when($type, function ($q, $type) {
                return $q->where('type', $type);
            })
            ->orderBy('created_at', 'ASC');

        $total = $query->count();
        $partners = $query->forPage($page, $perPage)->get();

        // Load SEKALI (bukan per-partner) — tetap jauh lebih murah daripada
        // sebelumnya (yang query ke Company/School + Feedback avg SATU-SATU
        // per baris partner). Fuzzy-match nama (incl. strip prefix PT./CV./
        // SMK/SMA) tetap dipertahankan, tapi dilakukan di memori PHP untuk
        // halaman saat ini saja (maks $perPage baris), bukan lewat query SQL
        // berulang.
        $allCompanies = \App\Models\Company::all(['id', 'user_id', 'name']);
        $allSchools = \App\Models\School::all(['id', 'user_id', 'name']);

        $findCompany = function (string $partnerName) use ($allCompanies) {
            $clean = mb_strtolower(preg_replace('/^(PT\.\s+|CV\.\s+)/i', '', $partnerName));
            $target = mb_strtolower($partnerName);
            return $allCompanies->first(
                fn ($c) => str_contains(mb_strtolower($c->name), $target) || str_contains(mb_strtolower($c->name), $clean)
            );
        };

        $findSchool = function (string $partnerName) use ($allSchools) {
            $clean = mb_strtolower(preg_replace('/^(SMK\s+|SMA\s+|SMKS\s+)/i', '', $partnerName));
            $target = mb_strtolower($partnerName);
            return $allSchools->first(
                fn ($s) => str_contains(mb_strtolower($s->name), $target) || str_contains(mb_strtolower($s->name), $clean)
            );
        };

        $matchedCompanies = collect();
        $matchedSchools = collect();
        foreach ($partners as $partner) {
            if ($partner->type === 'company') {
                if ($c = $findCompany($partner->name)) $matchedCompanies->put($partner->id, $c);
            } else {
                if ($s = $findSchool($partner->name)) $matchedSchools->put($partner->id, $s);
            }
        }

        $companyIds = $matchedCompanies->pluck('id')->unique()->values();
        $schoolIds = $matchedSchools->pluck('id')->unique()->values();

        $openingsByCompany = $companyIds->isEmpty()
            ? collect()
            : \App\Models\JobOpening::whereIn('company_id', $companyIds)
                ->where('is_available', true)
                ->selectRaw('company_id, COUNT(*) as total')
                ->groupBy('company_id')
                ->pluck('total', 'company_id');

        $studentsBySchool = $schoolIds->isEmpty()
            ? collect()
            : \App\Models\Student::whereIn('school_id', $schoolIds)
                ->selectRaw('school_id, COUNT(*) as total')
                ->groupBy('school_id')
                ->pluck('total', 'school_id');

        $companyUserIds = $matchedCompanies->pluck('user_id')->filter()->unique()->values();
        $schoolUserIds = $matchedSchools->pluck('user_id')->filter()->unique()->values();

        $companyRatings = $companyUserIds->isEmpty()
            ? collect()
            : \App\Models\Feedback::whereIn('to_user_id', $companyUserIds)
                ->where('to_type', 'company')
                ->selectRaw('to_user_id, AVG(rating) as avg_rating')
                ->groupBy('to_user_id')
                ->pluck('avg_rating', 'to_user_id');

        $schoolRatings = $schoolUserIds->isEmpty()
            ? collect()
            : \App\Models\Feedback::whereIn('to_user_id', $schoolUserIds)
                ->where('to_type', 'school')
                ->selectRaw('to_user_id, AVG(rating) as avg_rating')
                ->groupBy('to_user_id')
                ->pluck('avg_rating', 'to_user_id');

        foreach ($partners as $partner) {
            $openings_count = 0;
            $students_count = 0;
            $rating = 0.0;

            if ($partner->type === 'company') {
                $company = $matchedCompanies->get($partner->id);
                if ($company) {
                    $openings_count = (int) ($openingsByCompany[$company->id] ?? 0);
                    $ratingVal = $companyRatings[$company->user_id] ?? null;
                    $rating = $ratingVal ? round((float) $ratingVal, 1) : 0.0;
                }
            } else {
                $school = $matchedSchools->get($partner->id);
                if ($school) {
                    $students_count = (int) ($studentsBySchool[$school->id] ?? 0);
                    $ratingVal = $schoolRatings[$school->user_id] ?? null;
                    $rating = $ratingVal ? round((float) $ratingVal, 1) : 0.0;
                }
            }

            $partner->openings_count = $openings_count;
            $partner->students_count = $students_count;
            $partner->rating = $rating;
        }

        return response()->json([
            'data' => $partners,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil($total / max($perPage, 1)),
            ],
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
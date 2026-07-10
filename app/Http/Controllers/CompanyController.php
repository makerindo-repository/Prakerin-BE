<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/companies',
        summary: 'Menampilkan daftar perusahaan',
        tags: ['Company']
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        required: false,
        description: 'Jumlah data per halaman',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        required: false,
        description: 'Cari perusahaan berdasarkan nama',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil daftar perusahaan'
    )]
    public function index()
    {
        $limit = request()->query('limit', 10);
        $search = request()->query('search', '');
        $companies = Company::where('name', 'like', "%$search%")->with(['user'])->paginate($limit);

        return response()->json(
            $companies,
            200
        );
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
        path: '/companies/{id}',
        summary: 'Menampilkan detail perusahaan',
        tags: ['Company']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID Company',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil detail perusahaan'
    )]
    #[OA\Response(
        response: 404,
        description: 'Company tidak ditemukan'
    )]
    public function show(int $id)
    {
        // $company = Company::with(['user.profileImage', 'internships'])->find($id);

        // if (!$company) {
        //     throw new HttpResponseException(
        //         response()->json(['errors' => 'Company not found.'], 404)
        //     );
        // }


        // return response()->json(['data' => $company->user->load('company.internships')], 200);

        $company = Company::with(['user', 'internships'])->find($id);
        return response()->json(['data' => $company], 200);
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

    /**
     * Get the count of companies.
     */
    #[OA\Get(
        path: '/companies/count',
        summary: 'Menghitung jumlah perusahaan',
        tags: ['Company']
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil jumlah perusahaan'
    )]
    public function companyCount()
    {
        $companyCount = Company::count();
        return response()->json([
            'data' => $companyCount
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $limit = request()->query('limit', 10);
        $search = request()->query('search', '');
        $companies = Company::where('name', 'like', "%$search%")->with(['user.profileImage'])->paginate($limit);

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
    public function show(int $id)
    {
        // $company = Company::with(['user.profileImage', 'internships'])->find($id);

        // if (!$company) {
        //     throw new HttpResponseException(
        //         response()->json(['errors' => 'Company not found.'], 404)
        //     );
        // }


        // return response()->json(['data' => $company->user->load('company.internships')], 200);

        $company = Company::with(['user.profileImage', 'internships'])->find($id);
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

    public function companyCount()
    {
        $companyCount = Company::count();
        return response()->json([
            'data' => $companyCount
        ], 200);
    }
}

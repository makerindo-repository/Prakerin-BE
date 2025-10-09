<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Log;

class PartnerController extends Controller
{

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'required|string|max:255',
            'type' => 'required|in:school,company',
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
            'type' => 'required|in:school,company',
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

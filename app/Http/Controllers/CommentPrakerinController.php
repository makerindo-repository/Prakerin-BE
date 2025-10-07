<?php

namespace App\Http\Controllers;

use App\Models\CommentPrakerin;
use App\Models\CommnetPrakerin;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CommentPrakerinController extends Controller
{

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'photo_profile' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                ['errors' => $validator->errors()],
                400
            ));
        }

        $validated = $validator->validated();

        // Ambil file
        $file = $request->file('photo_profile');

        // Tentukan nama baru (misalnya pakai timestamp + original extension)
        $filename = time() . '.' . $file->getClientOriginalExtension();

        // Simpan ke storage/app/public/comment-prakerin dengan nama baru
        $file->storeAs('comment-prakerin', $filename, 'public');

        CommentPrakerin::create([
            'photo_profile' => $filename,
            'name' => $validated['name'],
            'position' => $validated['position'],
            'comment' => $validated['comment'],
        ]);

        return response()->json(['data' => true], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $commentPrakerin = CommentPrakerin::find($id);

        if (!$commentPrakerin) {
            throw new HttpResponseException(response()->json(
                ['errors' => "Ulasan tidak ditemukan!"],
                400
            ));
        }


        $validator = Validator::make($request->all(), [
            'photo_profile' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                ['errors' => $validator->errors()],
                400
            ));
        }


        $validated = $validator->validated();

        // Ambil file
        if (isset($validated['photo_profile'])) {
            $file = $request->file('photo_profile');

            // Tentukan nama baru (misalnya pakai timestamp + original extension)
            $filename = time() . '.' . $file->getClientOriginalExtension();

            // Simpan ke storage/app/public/partner dengan nama baru
            $file->storeAs('comment-prakerin', $filename, 'public');

            if (Storage::disk('public')->exists("comment-prakerin/{$commentPrakerin->photo_profile}")) {
                Storage::disk('public')->delete("comment-prakerin/{$commentPrakerin->photo_profile}");
            }

            $commentPrakerin->photo_profile = $filename;
        }


        $commentPrakerin->name = $validated['name'];
        $commentPrakerin->position = $validated['position'];
        $commentPrakerin->comment = $validated['comment'];
        $commentPrakerin->save();

        return response()->json(['data' => true], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $commentPrakerin = CommentPrakerin::find($id);

        if (!$commentPrakerin) {
            throw new HttpResponseException(response()->json(
                ['errors' => "Ulasan tidak ditemukan!"],
                400
            ));
        }


        if (Storage::disk('public')->exists("comment-prakerin/{$commentPrakerin->photo_profile}")) {
            Storage::disk('public')->delete("comment-prakerin/{$commentPrakerin->photo_profile}");
        }

        $commentPrakerin->delete();

        return response()->json([
            'data' => true
        ], 200);
    }
}

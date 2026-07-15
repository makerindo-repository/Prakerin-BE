<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuideRequest;
use App\Models\Guide;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GuideController extends Controller
{
    /**
     * Store a newly created guide (Admin only).
     */
    public function store(StoreGuideRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('guides', 'public');
            $data['file_path'] = $path;
        }

        $data['uploaded_by'] = Auth::id();

        $guide = Guide::create($data);

        return response()->json([
            'success' => true,
            'data' => $guide,
        ], 201);
    }

    /**
     * Display a listing of guides based on user role (Public).
     */
    public function index(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        $type = $request->query('type');

        // Fallback to user role if no type is explicitly specified
        if (!$type && $user) {
            $type = $user->role;
        }

        $query = Guide::where('is_published', true);

        if ($type) {
            $query->where('type', $type);
        }

        $guides = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $guides,
        ]);
    }

    /**
     * Display a listing of all guides for management (Admin only).
     */
    public function adminAll(Request $request)
    {
        $guides = Guide::with('uploadedBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $guides,
        ]);
    }

    /**
     * Display the specified guide.
     */
    public function show(string $id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            throw new HttpResponseException(response([
                "errors" => "Guide not found"
            ], 404));
        }

        return response()->json([
            'data' => $guide,
        ]);
    }

    /**
     * Update the specified guide (Admin only).
     */
    public function update(Request $request, string $id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            throw new HttpResponseException(response([
                "errors" => "Guide not found"
            ], 404));
        }

        $data = $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'type' => 'in:student,school,company',
            'is_published' => 'boolean',
            'file' => 'nullable|file|mimes:pdf|max:10240', // ganti file (opsional)
        ]);

        if ($request->hasFile('file')) {
            // Buang file lama supaya storage gak numpuk sampah.
            if ($guide->file_path) {
                Storage::disk('public')->delete($guide->file_path);
            }
            $data['file_path'] = $request->file('file')->store('guides', 'public');
        }

        $guide->update($data);

        return response()->json([
            'success' => true,
            'data' => $guide,
        ]);
    }

    /**
     * Remove the specified guide from storage (Admin only).
     */
    public function destroy(string $id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            throw new HttpResponseException(response([
                "errors" => "Guide not found"
            ], 404));
        }

        // Delete the file from storage
        if ($guide->file_path) {
            Storage::disk('public')->delete($guide->file_path);
        }

        $guide->delete();

        return response()->json([
            'success' => true,
            'message' => 'Guide deleted successfully',
        ]);
    }
}
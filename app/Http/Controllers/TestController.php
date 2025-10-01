<?php

namespace App\Http\Controllers;

use App\Models\Test;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search', '');
        $limit = $request->query('limit', 10);
        $type = $request->query('type', null);

        $tests = Test::where('company_id', $request->user()->company->id)
            ->where('title', 'like', "%$search%")
            ->when($type, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        return response()->json($tests, 200);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'link' => 'required|string|active_url|max:255',
            'description' => 'required|string',
            'type' => 'required|in:theory,practice,other',
        ]);

        if ($validated->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validated->errors(),
            ], 400));
        }

        $data = $validated->validated();

        $test = new Test();
        $test->company_id = $request->user()->company->id;
        $test->title = $data['title'];
        $test->link = $data['link'];
        $test->description = $data['description'];
        $test->type = $data['type'];
        $test->save();

        return response()->json([
            'data' => true,
        ], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Tes tidak ditemukan.',
            ], 404));
        }

        if ($test->company_id !== $request->user()->company->id) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Anda tidak memiliki izin untuk mengubah tes ini.',
            ], 403));
        }

        $validated = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'link' => 'sometimes|required|string|active_url|max:255',
            'description' => 'sometimes|required|string',
            'type' => 'sometimes|required|in:theory,practice,other',
        ]);

        if ($validated->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validated->errors(),
            ], 400));
        }

        $data = $validated->validated();

        foreach (['title', 'link', 'description', 'type'] as $field) {
            if (isset($data[$field])) {
                $test->$field = $data[$field];
            }
        }
        $test->save();

        return response()->json([
            'data' => true,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, Request $request)
    {
        $test = Test::find($id);

        if (!$test) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Tes tidak ditemukan',
            ], 404));
        }

        if ($test->company_id !== $request->user()->company->id) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Anda tidak memiliki izin untuk menghapus tes ini',
            ], 403));
        }

        $test->delete();

        return response()->json([
            'data' => true,
        ], 200);
    }
}

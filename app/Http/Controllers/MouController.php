<?php

namespace App\Http\Controllers;

use App\Models\Mou;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MouController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:draft,active,expired,rejected',
            'file' => 'required|file|mimes:pdf|max:2048',
            'mou_number' => 'nullable|string|max:255'
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
        $mou->status = $data['status'];


        if ($request->file('file')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('file')->getClientOriginalExtension();
            $mou->file = $filename;

            $request->file('file')->storeAs('mous', $filename);
        }

        $mou->mou_number = $data['mou_number'];
        if (auth()->user()->tokenCan('school-access')) {
            $mou->company_id = $data['company_id'];
            $mou->school_id = auth()->user()?->school->id;
        } else if (auth()->user()->tokenCan('company-access')) {
            $mou->company_id = auth()->user()?->company->id;
            $mou->school_id = $data['school_id'];
        }
        $mou->save();

        return response()->json(['data' => $mou], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
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
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'status' => 'sometimes|required|in:draft,active,expired,rejected',
            'file' => 'sometimes|required|file|mimes:pdf|max:2048',
            'mou_number' => 'sometimes|nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response([
                "errors" => $validator->getMessageBag()
            ], 400));
        }

        $data = $validator->validated();
        if (isset($data['file'])) {
            if (Storage::exists('mous/' . $mou->file)) {
                Storage::delete('mous/' . $mou->file);
            }

            $filename = now()->format('Ymd_His') . '.' . $data['file']->getClientOriginalExtension();
            $mou->file = $filename;

            $data['file']->storeAs('mous', $filename);
        }


        $mou->start_date = $data['start_date'] ?? $mou->start_date;
        $mou->end_date = $data['end_date'] ?? $mou->end_date;
        $mou->status = $data['status'] ?? $mou->status;
        $mou->mou_number = $data['mou_number'] ?? $mou->mou_number;

        $mou->save();

        return response()->json(['data' => $mou], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
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
}

<?php

namespace App\Http\Controllers;

use App\Models\ContactUs;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ContactUsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search', '');
        $isRead = $request->has('is_read')
            ? filter_var($request->query('is_read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $contactUs = ContactUs::where(function ($query) use ($search) {
            $query->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('message', 'like', "%$search%");
        })
            ->when($isRead !== null, function ($query) use ($isRead) {
                $query->where('is_read', $isRead);
            })
            ->paginate($limit);


        return response()->json([
            $contactUs
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required',
            'recaptcha_token' => 'required'
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json($validator->errors(), 400));
        }

        $data = $validator->validated();

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.nocaptcha.secret'),
            'response' => $data['recaptcha_token'],
        ]);

        if (!$response->json('success')) {
            throw new HttpResponseException(response([
                "errors" => "Captcha failed"
            ], 400));
        }

        $contactUs = ContactUs::create($data);

        return response()->json([
            'data' => $contactUs
        ], 201);

    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $contactUs = ContactUs::find($id);

        if (!$contactUs) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Contact Us message not found'
            ], 404));
        }

        $validator = Validator::make($request->all(), [
            'is_read' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 400));
        }

        $data = $validator->validated();

        $contactUs->update($data);

        return response()->json([
            'data' => $contactUs
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $contactUs = ContactUs::find($id);

        if (!$contactUs) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Contact Us message not found'
            ], 404));
        }

        $contactUs->delete();

        return response()->json([
            'message' => 'Contact Us message deleted successfully'
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
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
    #[OA\Get(
        path: '/contact-us',
        summary: 'Menampilkan daftar pesan Contact Us',
        tags: ['Contact Us']
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
        description: 'Cari berdasarkan nama, email, atau pesan',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'is_read',
        in: 'query',
        required: false,
        description: 'Filter status pesan',
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil mengambil data Contact Us'
    )]
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
    #[OA\Post(
        path: '/contact-us',
        summary: 'Mengirim pesan Contact Us',
        tags: ['Contact Us']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'message', 'recaptcha_token'],
            properties: [
                new OA\Property(
                    property: 'name',
                    type: 'string'
                ),
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    format: 'email'
                ),
                new OA\Property(
                    property: 'message',
                    type: 'string'
                ),
                new OA\Property(
                    property: 'recaptcha_token',
                    type: 'string'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Pesan berhasil dikirim'
    )]
    #[OA\Response(
        response: 400,
        description: 'Validasi gagal atau Captcha gagal'
    )]
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
    #[OA\Put(
        path: '/contact-us/{id}',
        summary: 'Mengubah status pesan Contact Us',
        tags: ['Contact Us']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['is_read'],
            properties: [
                new OA\Property(
                    property: 'is_read',
                    type: 'boolean'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Status pesan berhasil diubah'
    )]
    #[OA\Response(
        response: 404,
        description: 'Contact Us message tidak ditemukan'
    )]
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
    #[OA\Delete(
        path: '/contact-us/{id}',
        summary: 'Menghapus pesan Contact Us',
        tags: ['Contact Us']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Pesan berhasil dihapus'
    )]
    #[OA\Response(
        response: 404,
        description: 'Contact Us message tidak ditemukan'
    )]
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

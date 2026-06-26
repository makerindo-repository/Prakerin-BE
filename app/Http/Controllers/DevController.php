<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\User;
use Illuminate\Http\Request;

class DevController extends Controller
{

    #[OA\Post(
        path: '/dev/feed',
        summary: 'Developer Feed Testing',
        description: 'Endpoint untuk kebutuhan testing developer dalam memberikan relasi/rating antar user.',
        tags: ['Developer']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['id', 'to_id'],
            properties: [
                new OA\Property(
                    property: 'id',
                    type: 'string',
                    description: 'ID User yang memberikan rating'
                ),
                new OA\Property(
                    property: 'to_id',
                    type: 'string',
                    description: 'ID User yang menerima rating'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Berhasil memberikan rating'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation Error'
    )]
    public function devFeed(Request $request) {
        $request->validate([
            'id' => 'required',
            'to_id' => 'required'
        ]);
        $data = User::where('id', $request->to_id)->first();
        $data->rated()->attach($request->id);
        return response()->json($data, 200);
    }
    // 11123df8-f5bc-4ca6-ae20-3bff99e63c87
    // a1ab76bc-c1f9-41d4-b869-bca873a7826c
}

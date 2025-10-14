<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class DevController extends Controller
{
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

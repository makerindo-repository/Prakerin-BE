<?php

namespace App\Http\Controllers;


class AuthController extends Controller
{
    public function unauthorized()
    {
        return response()->json(['errors' => 'Unauthorized'], 401);
    }

}

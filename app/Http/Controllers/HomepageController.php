<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hompage;

class HomepageController extends Controller
{
    public function index(){
        $data = Hompage::where('name', 'LIKE', '%landing%')->get();
        return response()->json([
            'data' => $data
        ], 200);
    }
}

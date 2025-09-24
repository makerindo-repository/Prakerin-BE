<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hompage;
use App\Models\Company;
class HomepageController extends Controller
{
    public function index(){
        $data = Hompage::where('name', 'LIKE', '%landing%')->get();
        $company = Company::limit(10)->get();

        // Ubah format menjadi { name: value, ... }
        $formatted = [];
        foreach ($data as $item) {
            $formatted[$item->name] = $item->value;
        }
        $formatted['mitra'] = $company; 

        return response()->json($formatted, 200);
    }

    public function about() {
        $data = Hompage::where('name', 'LIKE', '%about%');
    }
}
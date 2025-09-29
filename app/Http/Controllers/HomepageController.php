<?php

namespace App\Http\Controllers;

use App\Http\Requests\HomePage\HomePageRequest;
use DB;
use Illuminate\Http\Request;
use App\Models\Hompage;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
class HomepageController extends Controller
{
    public function index()
    {
        $data = Hompage::where('name', 'LIKE', '%landing%')
            ->orderBy('created_at', 'ASC')
            ->get();
        $company = Company::limit(10)->get();


        $formatted = [];
        if ((!Auth::guard('sanctum')->user())) {
            foreach ($data as $item) {
                $formatted[$item->name] = $item->value;
            }
            $formatted['mitra'] = $company;
        } else {
            $formatted = $data;
        }


        return response()->json(['data' => $formatted], 200);
    }

    public function about()
    {
        $data = Hompage::where('name', 'LIKE', '%about%');
    }

    public function update(HomePageRequest $request)
    {
        $validated = $request->validated();

        $data = $validated['data'];

        // foreach ($data as $item) {
        //     // Update berdasarkan id
        //     Hompage::where('id', $item['id'])->update([
        //         'name' => $item['name'],
        //         'value' => $item['value'],
        //     ]);
        // }

        $ids = array_column($data, 'id');

        $valueCases = [];
        foreach ($data as $item) {
            $valueCases[] = "WHEN '{$item['id']}' THEN '{$item['value']}'";
        }

        $valueCasesSql = implode(' ', $valueCases);
        $idsSql = "'" . implode("','", $ids) . "'";

        DB::statement("
            UPDATE hompages
            SET value = CASE id
                $valueCasesSql
            END
            WHERE id IN ($idsSql)
        ");

        return response()->json(['data' => true], 200);
    }
}
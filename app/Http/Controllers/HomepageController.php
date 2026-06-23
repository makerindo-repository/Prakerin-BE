<?php

namespace App\Http\Controllers;

use App\Http\Requests\HomePage\HomePageRequest;
use App\Models\CommentPrakerin;
use App\Models\Partner;
use App\Models\JobOpening;
use DB;
use App\Models\Hompage;
use Illuminate\Support\Facades\Auth;
use Log;
class HomepageController extends Controller
{
    public function index()
    {
        $data = Hompage::where('name', 'LIKE', '%landing%')
            ->orderBy('created_at', 'ASC')
            ->get();
        $partner = Partner::orderBy('created_at', 'ASC')->get();
        $commentPrakerin = CommentPrakerin::orderBy('created_at', 'ASC')->get();
        $jobOpenings = JobOpening::orderBy('created_at', 'DESC')->get();

        $formatted = [];
        if ((!Auth::guard('sanctum')->user())) {
            foreach ($data as $item) {
                $formatted[$item->name] = $item->value;
            }
        } else {
            $formatted = $data;
        }


        return response()->json([
            'data' => [
                'homepages' => $formatted,
                'partners' => $partner,
                'comment_prakerins' => $commentPrakerin,
                'job_openings' => $jobOpenings
            ]
        ], 200);
    }

    public function about()
    {
        $data = Hompage::where('name', 'LIKE', '%about%')->get();

        return response()->json([
        'data' => $data
    ], 200);
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
<?php

namespace App\Http\Controllers;

use App\Models\JobOpening;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobOpeningController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $limit = request()->query('limit', 10);
        $search = request()->query('search', '');
        $province_id = request()->query('province_id', '');
        $city_regency_id = request()->query('city_regency_id', '');
        $grade = request()->query('grade', '');
        $field_id = request()->query('field_id', '');
        $duration_id = request()->query('duration_id', '');


        if (Auth::guard('sanctum')->user()?->tokenCan('company-access')) {

            $jobOpenings = JobOpening::with(
                [
                    'company.user',
                    'company.cityRegency.province',
                ]
            )
                ->where('company_id', Auth::guard('sanctum')->user()?->company->id)
                ->whereHas('company', function ($query) use ($province_id, $city_regency_id) {
                    if ($province_id && !$city_regency_id) {
                        $query->whereHas('cityRegency', function ($query) use ($province_id) {
                            $query->where('province_id', $province_id);
                        });
                    }
                    if ($city_regency_id) {
                        $query->where('city_regency_id', $city_regency_id);
                    }
                })
                ->where('title', 'like', "%{$search}%")
                ->when($grade === 'smk' || $grade === 'mahasiswa' || $grade === 'all', function ($query, $grade) {
                    return $query->where('grade', $grade);
                })
                ->when($field_id, function ($query, $field_id) {
                    return $query->where('field_id', $field_id);
                })
                ->when($duration_id, function ($query, $duration_id) {
                    return $query->where('duration_id', $duration_id);
                })
                ->paginate($limit);

            $jobOpenings->getCollection()->transform(function ($item) {
                return [
                    "id" => $item->id,
                    "company_id" => $item->company_id,
                    "field_id" => $item->field_id,
                    "duration_id" => $item->duration_id,
                    "title" => $item->title,
                    "description" => $item->description,
                    "grade" => $item->grade,
                    "type" => $item->type,
                    "location" => $item->location,
                    "qouta" => $item->qouta,
                    "is_paid" => $item->is_paid,
                    "is_available" => $item->is_available,
                    "created_at" => $item->created_at,
                    "updated_at" => $item->updated_at,
                    "company" => $item->company->makeHidden(['user', 'cityRegency']),
                    "city_regency" => $item->company->cityRegency->makeHidden(['province']),
                    "province" => $item->company->cityRegency->province,
                    'user' => $item->company->user,
                ];
            });
        } else {
            $isSaved = filter_var(request()->query('is_saved', false), FILTER_VALIDATE_BOOLEAN);


            $jobOpenings = JobOpening::with([
                'company.user',
                'saveJobOpening' => function ($query) {
                    $query->where('student_id', Auth::guard('sanctum')->user()?->student->id);
                }
            ])
                ->whereHas('company', function ($query) use ($province_id, $city_regency_id) {
                    if ($province_id && !$city_regency_id) {
                        $query->whereHas('cityRegency', function ($query) use ($province_id) {
                            $query->where('province_id', $province_id);
                        });
                    }
                    if ($city_regency_id) {
                        $query->where('city_regency_id', $city_regency_id);
                    }
                })
                ->where('title', 'like', "%{$search}%")
                ->when($grade === 'smk' || $grade === 'mahasiswa' || $grade === 'all', function ($query, $grade) {
                    return $query->where('grade', $grade);
                })
                ->when($field_id, function ($query, $field_id) {
                    return $query->where('field_id', $field_id);
                })
                ->when($duration_id, function ($query, $duration_id) {
                    return $query->where('duration_id', $duration_id);
                })
                ->where('is_available', true)
                ->when($isSaved, function ($query) {
                    $query->whereHas('saveJobOpening', function ($q) {
                        $q->where('student_id', Auth::guard('sanctum')->user()?->student->id);
                    });
                })
                ->paginate($limit);



            $jobOpenings->getCollection()->transform(function ($item) {
                return [
                    "id" => $item->id,
                    "company_id" => $item->company_id,
                    "field_id" => $item->field_id,
                    "duration_id" => $item->duration_id,
                    "title" => $item->title,
                    "description" => $item->description,
                    "grade" => $item->grade,
                    "type" => $item->type,
                    "location" => $item->location,
                    "qouta" => $item->qouta,
                    "is_paid" => $item->is_paid,
                    "is_available" => $item->is_available,
                    "created_at" => $item->created_at,
                    "updated_at" => $item->updated_at,
                    "company" => $item->company->makeHidden(['user', 'cityRegency']),
                    'user' => $item->company->user,
                    'save_job_opening' => $item->saveJobOpening->isNotEmpty() ? true : false,
                    'city_regency' => $item->company->cityRegency->makeHidden(['province']),
                    'province' => $item->company->cityRegency->province,
                ];
            });
        }



        return response()->json($jobOpenings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'field_id' => 'required|exists:fields,id',
            'duration_id' => 'required|exists:durations,id',
            'title' => 'required|string|max:255',
            'description' => 'required',
            'is_paid' => 'required|boolean',
            'grade' => 'required|in:smk,mahasiswa,all',
            'type' => 'required|in:part_time,full_time',
            'location' => 'required|in:onsite, remote, hybrid,field',
            'quota' => 'required|integer|min:1',
            'is_available' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 400));
        }


        $data = $validator->validated();
        $data['company_id'] = auth()->user()->company->id;

        $jobOpening = JobOpening::create($data);

        return response()->json([
            'data' => $jobOpening
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $jobOpening = JobOpening::with(
            [
                'company.user',
                "company.cityRegency.province",
                'saveJobOpening' => function ($query) {
                    if (!Auth::guard('sanctum')->user()?->student()) {
                    }
                }
            ]
        )->find($id);


        if (!$jobOpening) {
            throw new HttpResponseException(response()->json(['errors' => 'Job opening not found'], 404));
        }

        $jobOpening["city_regency"] = $jobOpening->company->cityRegency->makeHidden(['province']);
        $jobOpening["province"] = $jobOpening->company->cityRegency->province;
        $jobOpening["company"] = $jobOpening->company->makeHidden(['user', 'cityRegency']);
        $jobOpening["user"] = $jobOpening->company->user;
        $isSaved = $jobOpening->saveJobOpening->isNotEmpty() ? true : false;
        unset($jobOpening["saveJobOpening"]);

        $jobOpening['save_job_opening'] = $isSaved;

        return response()->json([
            'data' => $jobOpening,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $jobOpening = JobOpening::find($id);
        if (!$jobOpening) {
            throw new HttpResponseException(response()->json(['errors' => 'Job opening not found.'], 404));
        }

        if ($jobOpening->company_id !== auth()->user()->company->id) {
            throw new HttpResponseException(response()->json(['errors' => 'Forbidden.'], 403));
        }

        $validator = Validator::make($request->all(), [
            'field_id' => 'sometimes|required|exists:fields,id',
            'duration_id' => 'sometimes|required|exists:durations,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'is_paid' => 'sometimes|required|boolean',
            'grade' => 'sometimes|required|in:smk,mahasiswa,all',
            'type' => 'sometimes|required|in:wfh,fulltime,hybrid',
            'qouta' => 'sometimes|required|integer|min:1',
            'is_available' => 'sometimes|required|boolean'
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 400));
        }

        $data = $validator->validated();

        $jobOpening->update($data);
        $jobOpening->save();

        return response()->json([
            'data' => $jobOpening
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $jobOpening = JobOpening::find($id);
        if (!$jobOpening) {
            throw new HttpResponseException(response()->json(['errors' => 'Job opening not found.'], 404));
        }

        if ($jobOpening->company_id !== auth()->user()->company->id) {
            throw new HttpResponseException(response()->json(['errors' => 'Forbidden.'], 403));
        }

        $jobOpening->delete();

        return response()->json([
            'data' => 'Job opening deleted successfully'
        ], 200);
    }

    public function count(Request $request)
    {

        $count = JobOpening::when($request->user()->tokenCan("company-access"), function ($query) use ($request) {
            $query->where("company_id", $request->user()->company->id);
        })
            ->where("is_available", true)
            ->count();

        return response()->json([
            'data' => $count
        ]);
    }
}

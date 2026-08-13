<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserCreateRequest;
use App\Http\Requests\User\UserLoginRequest;
use App\Http\Requests\User\UserRegisterRequest;
use App\Http\Requests\User\UserUpdateProfileRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Jobs\FetchUniversityLogo;
use App\Models\Company;
use App\Models\Mou;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\Setting;
use App\Models\ActivityLog;
use Auth;
use DB;
use Http;
use Log;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
 * @OA\Get(
 *     path="/api/users",
 *     tags={"User"},
 *     summary="Get list of users",
 *     description="Retrieve users based on role, verification status, internship status, MOU status, and search filters.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="role", in="query", @OA\Schema(type="string", enum={"student","school","company"})),
 *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
 *     @OA\Parameter(name="is_verified", in="query", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="is_mou", in="query", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="is_completed", in="query", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="is_school", in="query", @OA\Schema(type="boolean")),
 *
 *     @OA\Response(
 *         response=200,
 *         description="User list retrieved successfully"
 *     ),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
    public function index(Request $request)
    {
        $isVerified = filter_var($request->query('is_verified', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');
        $limit = $request->query('limit', 10);
        $role = $request->query('role', null);
        $status = $request->query('status', null);
        $isMou = $request->has('is_mou')
            ? filter_var($request->query('is_mou'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $isCompleted = $request->has('is_completed')
            ? filter_var($request->query('is_completed'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $isSchool = $request->has('is_school')
            ? filter_var($request->query('is_school'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;



        $user = Auth::guard('sanctum')->user();


        $users = User::when($user === null, function ($query) use ($search, $isSchool) {
            $query->with(['school']);
            $query->where('role', 'school');
            $query->whereHas('school', function ($q) use ($search, $isSchool) {
                $q->where('is_verified', true);
                $q->where('name', 'like', "%$search%");
                $q->where('type', $isSchool ? 'school' : 'university');
            });
        })
            ->when($user?->tokenCan('school-access') && ($role === 'student'), function ($query,) use ($search, $isVerified, $user, $status, $request) {
                $query->with(
                    'student.major',
                    'student.curriculumVitae.internshipApplications.jobOpening.company.user',
                    'student.curriculumVitae.internshipApplications.jobOpening.company.cityRegency.province'
                );
                $query->where('role', 'student');
                $query->whereHas('student', function ($q) use ($isVerified, $user) {
                    $q->where('is_verified', $isVerified);
                    $q->where('school_id', $user->school->id);
                    if ($user->school->type === 'school') {
                        if ($isVerified) {
                            $q->where(function ($subQuery) {
                                $subQuery->whereIn('class', ['10', '11', '12', '13', '14'])
                                    ->orWhereNull('class')
                                    ->orWhere('class', '');
                            });
                        }
                    }
                });
                if ($search !== '' && $search !== null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('username', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhereHas('student', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
                    });
                }
                $query->when(in_array($status, ['ongoing', 'completed', 'not_started']), function ($query) use ($status) {
                    $query->whereHas('student', function ($q) use ($status) {
                        $q->where('status_magang', $status);
                    });
                });
                // Guard: halaman "Data Siswa" (school_type=school) / "Data Mahasiswa"
                // (school_type=university) cuma boleh nampilin data kalau institusi
                // login ini benar-benar sesuai tipe itu. Ini murni pengaman UI (salah
                // buka page); institusi tetap hanya bisa lihat siswanya sendiri
                // (sudah dibatasi school_id di atas).
                $schoolType = $request->query('school_type');
                $query->when($schoolType, function ($query) use ($schoolType, $user) {
                    if ($user->school->type !== $schoolType) {
                        $query->whereRaw('1 = 0');
                    }
                });
            })
            ->when(($user?->tokenCan('school-access') || $user?->tokenCan('student-access')) && ($role === 'company'), function ($query) use ($search, $isMou, $user) {
                $query->with(['company.cityRegency.province', 'company.mous']);
                $query->where('role', 'company');
                $query->whereHas('company', function ($q) use ($search, $isMou, $user) {
                    $q->where('is_verified', true);
                    $q->where('name', 'like', "%$search%");
                    $q->whereNotNull('city_regency_id');

                    if ($isMou === true) {
                        $q->whereHas('mous', function ($q2) use ($user) {
                            if ($user->tokenCan('student-access')) {
                                $q2->where('school_id', $user->student->school_id)
                                    ->where('status', 'accepted');
                            } else {
                                $q2->where('school_id', $user->school->id)
                                    ->where('status', 'accepted');
                            }
                        });
                    } elseif ($isMou === false) {
                        $q->whereDoesntHave('mous', function ($q2) use ($user) {
                            if ($user->tokenCan('student-access')) {
                                $q2->where('school_id', $user->student->school_id)
                                    ->where('status', 'accepted');
                            } else {
                                $q2->where('school_id', $user->school->id)
                                    ->where('status', 'accepted');
                            }
                        });
                    }
                });
            })
            ->when($user?->tokenCan('company-access') && ($role === 'school'), function ($query) use ($search, $isMou, $user) {
                $query->with(['school.mous', 'school.cityRegency.province']);
                $query->where('role', 'school');
                $query->whereHas('school', function ($q) use ($search, $isMou, $user) {
                    $q->where('is_verified', true);
                    $q->where('name', 'like', "%$search%");

                    if ($isMou === true) {
                        $q->whereHas('mous', function ($q2) use ($user) {
                            $q2->where('company_id', $user->company->id)
                                ->where('status', 'accepted');
                        });
                    } elseif ($isMou === false) {
                        $q->whereDoesntHave('mous', function ($q2) use ($user) {
                            $q2->where('company_id', $user->company->id)
                                ->where('status', 'accepted');
                        });
                    }
                });
            })
            ->when($user?->tokenCan('company-access') && ($role === 'student'), function ($query) use ($user, $search, $isCompleted) {
                $query->with(['student.school', 'student.internships.internshipApplication.jobOpening']);
                $query->where('role', 'student');
                $query->whereHas('student.internships', function ($query) use ($user, $isCompleted) {
                    $query->where('company_id', $user->company->id);
                    $query->when($isCompleted !== null, function ($query) use ($isCompleted) {
                        $query->where('is_completed', $isCompleted);
                    });
                });
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            })
            ->when($user?->tokenCan('admin-access'), function ($query) use ($role, $request, $search, $isSchool) {
                $schoolId = $request->query('school_id');
                $schoolType = $request->query('school_type'); // 'school' or 'university'
                $isVerified = $request->has('is_verified')
                    ? filter_var($request->query('is_verified'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    : null;

                $query->with(['student.major', 'school', 'company']);
                $query->when($role, function ($query, $role) use ($isSchool) {
                    return $query->where('role', $role)
                        ->when($role === 'school', function($q) use ($isSchool) {
                            $q->whereHas('school', function ($sq) use ($isSchool) {
                                if ($isSchool !== null) {
                                    $sq->where('type', $isSchool ? 'school' : 'university');
                                }
                            });
                        })
                        ->when($role === 'company', fn($q) => $q->whereHas('company'))
                        ->when($role === 'student', fn($q) => $q->whereHas('student'));
                });

                $query->when(
                    $role === 'student' && $schoolId,
                    function ($query) use ($schoolId) {
                        $query->where('role', 'student');
                        $query->whereHas('student', function ($q) use ($schoolId) {
                            $q->where('school_id', $schoolId);
                        });
                    }
                );

                // Filter students by school type (school = SMK/Siswa, university = Mahasiswa)
                $query->when(
                    $role === 'student' && $schoolType,
                    function ($query) use ($schoolType, $isVerified) {
                        $query->whereHas('student.school', function ($q) use ($schoolType) {
                            $q->where('type', $schoolType);
                        });
                        if ($schoolType === 'school') {
                            $query->whereHas('student', function ($q) use ($isVerified) {
                                if ($isVerified !== false) {
                                    $q->where(function ($subQuery) {
                                        $subQuery->whereIn('class', ['11', '12'])
                                            ->orWhereNull('class')
                                            ->orWhere('class', '');
                                    });
                                }
                            });
                        }
                    }
                );

                $query->when($isVerified !== null, function ($query) use ($isVerified,) {
                    $query->where(function ($q) use ($isVerified) {

                        $q->whereHas('student', fn($q2) => $q2->where('is_verified', $isVerified))
                            ->orWhereHas('school', fn($q2) => $q2->where('is_verified', $isVerified))
                            ->orWhereHas('company', fn($q2) => $q2->where('is_verified', $isVerified));
                    });
                });

                if ($search !== '' && $search !== null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('username', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhereHas('student', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                          ->orWhereHas('school', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                          ->orWhereHas('company', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
                    });
                }
            })
            ->when(!empty($search), function ($q) use ($search) {
                $searchLower = strtolower($search);
                $q->orderByRaw("
                    CASE
                        WHEN EXISTS (SELECT 1 FROM students WHERE students.user_id = users.id AND LOWER(students.name) = ?) THEN 1
                        WHEN EXISTS (SELECT 1 FROM students WHERE students.user_id = users.id AND LOWER(students.name) LIKE ?) THEN 2
                        WHEN LOWER(users.username) = ? THEN 3
                        WHEN LOWER(users.username) LIKE ? THEN 4
                        WHEN EXISTS (SELECT 1 FROM students WHERE students.user_id = users.id AND LOWER(students.name) LIKE ?) THEN 5
                        WHEN LOWER(users.username) LIKE ? THEN 6
                        ELSE 7
                    END ASC
                ", [
                    $searchLower,
                    "{$searchLower}%",
                    $searchLower,
                    "{$searchLower}%",
                    "%{$searchLower}%",
                    "%{$searchLower}%",
                ]);
            })
            ->orderBy('updated_at', 'desc')
            ->paginate($limit)
            ->through(function ($item) use ($user, $role) {
                if ($user === null) {
                    return [
                        'id' => $item->school?->id,
                        'name' => $item->school?->name,
                    ];
                } else if (($user?->tokenCan('school-access') || $user?->tokenCan('admin-access')) && ($role === 'student')) {

                    $schoolObj = $item->student?->school ? [
                        'id' => $item->student->school->id,
                        'name' => $item->student->school->name,
                        'type' => $item->student->school->type,
                    ] : null;

                    return [
                        'id' => $item->id,
                        'username' => $item->username,
                        'email' => $item->email,
                        'role' => $item->role,
                        'photo_profile' => $item->photo_profile,
                        'school' => $schoolObj,
                        'school_name' => $item->student?->school?->name ?? $item->student?->school_name ?? null,
                        'student' => [
                            'id' => $item->student?->id,
                            'name' => $item->student?->name,
                            'class' => $item->student?->class,
                            'school_id' => $item->student?->school_id,
                            'school_name' => $item->student?->school?->name ?? $item->student?->school_name ?? null,
                            'school' => $schoolObj,
                            'status_magang' => $item->student?->status_magang ?? 'not_started',
                            'status_subscription' => $item->student?->status_subscription ?? 'free',
                            'company' => $item->student?->curriculumVitae
                                ?->flatMap->internshipApplications
                                ?->map->jobOpening
                                ?->map->company
                                ?->unique('id')
                                ?->map(function ($company) {
                                    if (!$company) return null;
                                    $data = $company->toArray();
                                    $data['item'] = $company->item?->toArray();
                                    $data['city_regency'] = $company->cityRegency?->toArray();
                                    $data['province'] = $company->cityRegency?->province?->toArray();
                                    return $data;
                                })
                                ?->filter()
                                ?->values() ?? [],
                        ],
                        'major' => [
                            'name' => $item->student?->major?->name
                        ],
                        'status' => $item->student?->status_magang ?? 'not_started',
                        'status_magang' => $item->student?->status_magang ?? 'not_started',
                        'status_subscription' => $item->student?->status_subscription ?? 'free',
                    ];
                } else if (($user?->tokenCan('school-access') || $user?->tokenCan('student-access')) && ($role === 'company')) {
                    return [
                        'id' => $item->id,
                        'username' => $item->username,
                        'email' => $item->email,
                        'role' => $item->role,
                        'photo_profile' => $item->photo_profile,
                        'company' => $item->company?->makeHidden(['cityRegency', 'mous']),
                        'city_regency' => $item->company?->cityRegency?->makeHidden(['province']),
                        'province' => $item->company?->cityRegency?->province,
                        'mou' => $item->company ? !$item->company->mous->where('status', 'accepted')->isEmpty() : false,
                    ];
                } else if ($user?->tokenCan('company-access') && ($role === 'school')) {
                    return [
                        'id' => $item->id,
                        'username' => $item->username,
                        'email' => $item->email,
                        'role' => $item->role,
                        'photo_profile' => $item->photo_profile,
                        'name' => $item->school?->name,
                        'school' => $item->school?->makeHidden(['mous', 'cityRegency']),
                        'mou' => $item->school ? !$item->school->mous->where('status', 'accepted')->isEmpty() : false,
                        'city_regency' => $item->school?->cityRegency?->makeHidden(['province']),
                        'province' => $item->school?->cityRegency?->province
                    ];
                } else if ($user?->tokenCan('company-access') && ($role === 'student')) {
                    return [
                        'id' => $item->id,
                        'username' => $item->username,
                        'email' => $item->email,
                        'phone_number' => $item->phone_number,
                        'photo_profile' => $item->photo_profile,
                        'student' => $item->student,
                        'school' => $item->student?->school,
                        'internship' => $item->student?->internships?->first(),
                        'field' => $item->student?->internships?->first()?->internshipApplication?->jobOpening?->field?->name ?? null
                    ];
                }

                return $item;
            });


        return response()->json($users, 200);
    }


    /**
 * @OA\Post(
 *     path="/api/users",
 *     tags={"User"},
 *     summary="Create new user",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"username","email","password","role","name"},
 *                 @OA\Property(property="username",type="string"),
 *                 @OA\Property(property="email",type="string",format="email"),
 *                 @OA\Property(property="password",type="string"),
 *                 @OA\Property(property="role",type="string",enum={"student","school","company"}),
 *                 @OA\Property(property="name",type="string"),
 *                 @OA\Property(property="address",type="string"),
 *                 @OA\Property(property="school_id",type="string"),
 *                 @OA\Property(property="type",type="string"),
 *                 @OA\Property(property="image",type="string",format="binary")
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(response=201,description="User created successfully"),
 *     @OA\Response(response=400,description="Validation error")
 * )
 */
    public function store(UserCreateRequest $request)
    {

        $data = $request->validated();

        if (auth()->user()->tokenCan('school-access') && $data['role'] !== 'student') {
            throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 400));
        }


        $user = new User();
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->password = $data['password'];

        if ($request->file('image')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('image')->getClientOriginalExtension();
            $user->photo_profile = $filename;
            $request->file('image')->storeAs('photo-profile', $filename, 'public');
        }
        $user->save();


        if ($user->role === "student") {
            $student = new Student();
            $student->name = $data['name'];
            if (auth()->user()->tokenCan('school-access')) {
                $student->school_id = auth()->user()->school->id;
                $student->is_verified = true;
            } else {
                $student->school_id = $data['school_id'];
                $student->is_verified = true;
            }
            $student->user_id = $user->id;
            $student->class = $data['class'] ?? null;
            $student->major_id = $data['major_id'] ?? null;
            $student->gender = $data['gender'] ?? null;
            $student->address = $data['address'] ?? null;
            $student->phone_number = $data['phone_number'] ?? null;
            $student->date_of_birth = $data['date_of_birth'] ?? null;
            $student->save();
            $schoolType = School::find($student->school_id)?->type;
            $user->syncSpatieRole($schoolType);
        } else if ($user->role === "school") {
            $school = new School();
            $school->name = $data['name'];
            $school->address = $data['address'] ?? '';
            $school->user_id = $user->id;
            $school->type = $data['type'] ?? 'school';
            $school->is_verified = true;
            $school->save();
            $user->syncSpatieRole($school->type);
        } else if ($user->role === "company") {
            $company = new Company();
            $company->name = $data['name'];
            $company->address = $data['address'] ?? '';
            $company->user_id = $user->id;
            $company->is_verified = true;
            $company->save();
            $user->syncSpatieRole();
        }


        return response()->json([
            'data' => $user->load('student', 'school', 'company')
        ], 201);
    }


    /**
 * @OA\Get(
 *     path="/api/users/{id}",
 *     tags={"User"},
 *     summary="Get user detail",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\Response(response=200,description="User detail"),
 *     @OA\Response(response=404,description="User not found")
 * )
 */
    public function show(string $id, Request $request)
    {
        $user = User::with([
            'student' => fn($q) => $q->where('is_verified', true),
            'school' => fn($q) => $q->where('is_verified', true),
            'company' => fn($q) => $q->where('is_verified', true),
        ])->find($id);

        if (!$user) {
            throw new HttpResponseException(response([
                "errors" => "User not found."
            ], 404));
        }

        // Hide empty relationships
        $user->makeVisible(['student', 'school', 'company']);

        if ($user->role === 'company' && $user->company) {
            $user->company->load([
                'cityRegency.province',
                'sector',
                'jobOpenings' => function ($q) {
                    $q->where('is_available', true)->orderBy('created_at', 'desc');
                }
            ]);

            $mou = false;

            if ($request->user()->tokenCan('school-access')) {
                $mou = $user->company
                    ->mous()
                    ->where('school_id', $request->user()->school->id)
                    ->where('status', 'accepted')
                    ->exists();
            } else if ($request->user()->tokenCan('student-access')) {
                $mou = $user->company
                    ->mous()
                    ->where('school_id', $request->user()->student->school->id)
                    ->where('status', 'accepted')
                    ->exists();
            }

            $user = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'photo_profile' => $user->photo_profile,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'company' => $user->company ? $user->company->makeHidden(['cityRegency', 'sector', 'jobOpenings']) : null,
                'city_regency' => $user->company?->cityRegency ? $user->company->cityRegency->makeHidden(['province']) : null,
                'province' => $user->company?->cityRegency?->province,
                'sector' => $user->company?->sector,
                'job_openings' => $user->company?->jobOpenings,
                'mou' => $mou
            ];
        } else if ($user->role === 'school' && $user->school) {
            $user->school->load(['cityRegency.province']);

            $mou = false;

            if ($request->user()->tokenCan('company-access')) {
                $mou = $user->school
                    ->mous()
                    ->where('company_id', $request->user()->company->id)
                    ->where('status', 'accepted')
                    ->exists();
            }

            $user = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'photo_profile' => $user->photo_profile,
                'school' => $user->school ? $user->school->makeHidden(['cityRegency']) : null,
                'city_regency' => $user->school?->cityRegency ? $user->school->cityRegency->makeHidden(['province']) : null,
                'province' => $user->school?->cityRegency?->province,
                'mou' => $mou,
            ];
        }

        return response()->json([
            'data' => $user,
        ], 200);
    }

    /**
 * @OA\Put(
 *     path="/api/users/{id}",
 *     tags={"User"},
 *     summary="Verify or update user",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="is_verified",type="boolean")
 *         )
 *     ),
 *
 *     @OA\Response(response=200,description="User updated"),
 *     @OA\Response(response=404,description="User not found")
 * )
 */
    public function update(Request $request, UserUpdateRequest $userUpdateRequest, string $id)
    {
        $user = User::with(['student', 'school', 'company'])->find($id);
        if (!$user) {
            throw new HttpResponseException(response([
                "errors" => "Pengguna tidak ditemukan."
            ], 404));
        }

        $validator = Validator::make($request->all(), $userUpdateRequest->rules());


        $data = $validator->validated();

        if ($request->user()->tokenCan('school-access')) {
            if ($user->student === null) {
                throw new HttpResponseException(response([
                    "errors" => "Siswa tidak ditemukan."
                ], 404));
            }

            $isVerified = $data['is_verified'];




            if ($isVerified) {
                $user->student->is_verified = true;
                $user->student->save();

                return response()->json([
                    'data' => true,
                ], 200);
            } else {

                $user->student->school_id = null;
                $user->student->save();

                return response()->json([
                    'data' => true,
                ], 200);
            }
        }

        if ($user->role === 'school') {
            $school = $user->school;
            $school->is_verified = $data['is_verified'] ?? $school->is_verified;
            $school->save();
        } else if ($user->role === 'company') {
            $company = $user->company;
            $company->is_verified = $data['is_verified'] ?? $company->is_verified;
            $company->save();
        }

        $user->save();

        return response()->json([
            'data' => $user->load(['student', 'school', 'company']),
        ], 200);

    }

    /**
 * @OA\Delete(
 *     path="/api/users/{id}",
 *     tags={"User"},
 *     summary="Delete user",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\Response(response=200,description="User deleted"),
 *     @OA\Response(response=404,description="User not found")
 * )
 */
    public function destroy(string $id)
    {
        $user = User::with(['student'])->find($id);
        if (!$user) {
            throw new HttpResponseException(response([
                "errors" => "User not found."
            ], 404));
        }

        $currentUser = auth()->user();
        if ($currentUser->tokenCan('school-access') && !$currentUser->tokenCan('admin-access')) {
            if ($user->role !== 'student' || !$user->student || $user->student->school_id !== $currentUser->school?->id) {
                throw new HttpResponseException(response([
                    "errors" => "Unauthorized. You can only delete students belonging to your school."
                ], 403));
            }
        }

        if ($user->photo_profile) {
            if (Storage::exists("photo-profile/{$user->photo_profile}")) {
                Storage::delete("photo-profile/{$user->photo_profile}");
            }
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ], 200);
    }

    /**
 * @OA\Post(
 *     path="/api/register",
 *     tags={"Authentication"},
 *     summary="Register new user",
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"username","email","password","role","name","recaptcha_token"},
 *                 @OA\Property(property="username",type="string"),
 *                 @OA\Property(property="email",type="string",format="email"),
 *                 @OA\Property(property="password",type="string"),
 *                 @OA\Property(property="role",type="string"),
 *                 @OA\Property(property="name",type="string"),
 *                 @OA\Property(property="recaptcha_token",type="string"),
 *                 @OA\Property(property="image",type="string",format="binary")
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(response=201,description="Register success"),
 *     @OA\Response(response=400,description="Captcha failed")
 * )
 */
    public function register(UserRegisterRequest $request, User $user)
    {
        $data = $request->validated();

        /* CAPTCHA disabled for local dev - skip verification when no token provided
        if (!empty($data['recaptcha_token'])) {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.nocaptcha.secret'),
                'response' => $data['recaptcha_token'],
            ]);

            if (!$response->json('success')) {
                throw new HttpResponseException(response([
                    "errors" => [
                        "message" => "Captcha failed"
                    ]
                ], 400));
            }
        }
        */

        $user = new User();
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->password = $data['password'];

        if ($request->file('image')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('image')->getClientOriginalExtension();
            $user->photo_profile = $filename;
            $request->file('image')->storeAs('photo-profile', $filename, 'public');
        }
        $user->save();

        // JANGAN biarkan kegagalan kirim email verifikasi menggagalkan
        // seluruh proses registrasi (akun/student/school/company udah
        // kesimpen duluan sebelum baris ini). Kalau SMTP lagi bermasalah
        // (host gak nyambung, kredensial salah, dll), user tetap harus
        // dapat akun & token-nya — verifikasi email bisa dikirim ulang
        // belakangan lewat fitur "Kirim Ulang Verifikasi".
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[register] Gagal mengirim email verifikasi: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);
        }


        $token = null;

        if ($user->role === "student") {
            $student = new Student();
            $student->name = !empty($data['name']) ? $data['name'] : $data['username'];
            $student->school_id = $data['school_id'] ?? null;
            $student->user_id = $user->id;
            $student->is_verified = (bool) Setting::getVal('auto_approve_students', false);
            $student->save();
            $schoolType = !empty($data['school_id']) ? School::find($data['school_id'])?->type : null;
            $user->syncSpatieRole($schoolType);
            $token = $user->createToken('Auth Token', ['student-access'])->plainTextToken;
        } else if ($user->role === "school") {
            $school = new School();
            $school->name = !empty($data['name']) ? $data['name'] : $data['username'];
            $school->address = $data['address'] ?? '';
            $school->user_id = $user->id;
            $school->type = $data['type'] ?? 'school';
            $school->is_verified = (bool) Setting::getVal('auto_approve_schools', false);
            $school->save();
            $user->syncSpatieRole($school->type);
            $token = $user->createToken('Auth Token', ['school-access'])->plainTextToken;
        } else if ($user->role === "company") {
            $company = new Company();
            $company->name = !empty($data['name']) ? $data['name'] : $data['username'];
            $company->address = $data['address'] ?? '';
            $company->user_id = $user->id;
            $company->is_verified = (bool) Setting::getVal('auto_approve_companies', false);
            $company->save();
            $user->syncSpatieRole();
            $token = $user->createToken('Auth Token', ['company-access'])->plainTextToken;
        }

        if (!$token) {
            throw new HttpResponseException(response([
                "errors" => "Failed to create token"
            ], 500));
        }

        return response()->json(['token' => $token, 'role' => $user->role], 201);
    }

    /**
 * @OA\Post(
 *     path="/api/login",
 *     tags={"Authentication"},
 *     summary="Login",
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email","password","recaptcha_token"},
 *             @OA\Property(property="email",type="string",format="email"),
 *             @OA\Property(property="password",type="string"),
 *             @OA\Property(property="recaptcha_token",type="string")
 *         )
 *     ),
 *
 *     @OA\Response(response=200,description="Login success"),
 *     @OA\Response(response=401,description="Invalid credentials")
 * )
 */
    public function login(UserLoginRequest $request)
    {
        $data = $request->validated();

        /* CAPTCHA disabled for local dev - skip verification when no token provided
        if (!empty($data['recaptcha_token'])) {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.nocaptcha.secret'),
                'response' => $data['recaptcha_token'],
            ]);

            \Log::info('Recaptcha verify response', $response->json());

            if (!$response->json('success')) {
                return response()->json([
                    "message" => "Captcha failed",
                    "errors" => $response->json('error-codes'),
                ], 400);
            }
        }
        */

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                "message" => "Email atau password salah!",
                "errors" => null
            ], 401);
        }

        $token = null;

        $isVerified = true;
        if ($user->role === "student") {
            $token = $user->createToken('Auth Token', ['student-access'])->plainTextToken;
            $isVerified = (bool) Student::where('user_id', $user->id)->value('is_verified');
        } else if ($user->role === "school") {
            $token = $user->createToken('Auth Token', ['school-access'])->plainTextToken;
            $isVerified = (bool) School::where('user_id', $user->id)->value('is_verified');
        } else if ($user->role === "company") {
            $token = $user->createToken('Auth Token', ['company-access'])->plainTextToken;
            $isVerified = (bool) Company::where('user_id', $user->id)->value('is_verified');
        } else if ($user->role === "super_admin") {
            $token = $user->createToken('Auth Token', ['admin-access'])->plainTextToken;
            $isVerified = true;
        }

        if (!$token) {
            throw new HttpResponseException(response([
                "errors" => "Failed to create token"
            ], 500));
        }

        $user->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'resource_name' => $user->username,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => "User logged in: " . $user->username,
        ]);

        return response()->json(['token' => $token, 'role' => $user->role, 'is_verified' => $isVerified], 200);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Format email tidak valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Email tidak terdaftar dalam sistem.'
            ], 404);
        }

        // Generate 6-digit numeric OTP
        $otp = sprintf("%06d", mt_rand(0, 999999));

        // Store OTP hash in password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        // Attempt sending email notification safely
        try {
            $user->notify(new \App\Notifications\SendOtpNotification($otp));
        } catch (\Throwable $e) {
            Log::warning("Failed sending OTP email to {$request->email}: " . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'Kode OTP telah dikirim ke email Anda.',
            'debug_otp' => config('app.debug') ? $otp : null,
        ], 200);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Kode OTP harus 6 digit.',
                'errors' => $validator->errors()
            ], 422);
        }

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (!$record) {
            return response()->json([
                'status' => false,
                'message' => 'Kode OTP tidak ditemukan atau telah kedaluwarsa.'
            ], 400);
        }

        // Check 15 minutes expiration
        if (\Carbon\Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'status' => false,
                'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode OTP baru.'
            ], 400);
        }

        if (!Hash::check($request->otp, $record->token)) {
            return response()->json([
                'status' => false,
                'message' => 'Kode OTP tidak sesuai. Silakan periksa kembali email Anda.'
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Kode OTP valid.'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal. Pastikan password minimal 8 karakter dan konfirmasi cocok.',
                'errors' => $validator->errors()
            ], 422);
        }

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (!$record || !Hash::check($request->otp, $record->token)) {
            return response()->json([
                'status' => false,
                'message' => 'Kode OTP tidak valid atau telah kedaluwarsa.'
            ], 400);
        }

        // Check 15 minutes expiration
        if (\Carbon\Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'status' => false,
                'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode OTP baru.'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Pengguna tidak ditemukan.'
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'reset_password',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'resource_name' => $user->username,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => "User reset password via OTP: " . $user->username,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Password berhasil diperbarui. Silakan login dengan password baru Anda.'
        ], 200);
    }

    /**
 * @OA\Post(
 *     path="/api/logout",
 *     tags={"Authentication"},
 *     summary="Logout",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200,description="Logout success")
 * )
 */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'logout',
                'resource_type' => 'User',
                'resource_id' => $user->id,
                'resource_name' => $user->username,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'description' => "User logged out: " . $user->username,
            ]);
            $user->currentAccessToken()->delete();
        }
        return response()->json(['message' => 'Logout success'], 200);
    }

    /**
 * @OA\Get(
 *     path="/api/profile",
 *     tags={"User"},
 *     summary="Current user profile",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200,description="Profile retrieved")
 * )
 */
    public function profile(Request $request)
    {
        $user = $request->user()
            ->with(['student.school.cityRegency.province', 'school.cityRegency.province', 'company.cityRegency.province'])
            ->find($request->user()->id);


        if (!$user->student) {
            $user->makeHidden('student');
        }
        if (!$user->school) {
            $user->makeHidden('school');
        }
        if (!$user->company) {
            $user->makeHidden('company');
        }

        // Tentukan zona waktu dashboard berdasarkan provinsi institusi/sekolah
        // pengguna (WIB/WITA/WIT), bukan zona waktu perangkat/browser.
        $provinceName = null;

        if ($user->company) {
            $user->name = $user->company->name;
            if (isset($user->company->cityRegency)) {
                $user['company']["province_id"] = $user->company->cityRegency->province_id;
                $provinceName = $user->company->cityRegency->province->name ?? null;
            } else {
                $user['company']["province_id"] = null;
            }
        }
        if ($user->school) {
            $user->name = $user->school->name;
            if (isset($user->school->cityRegency)) {
                $user['school']["province_id"] = $user->school->cityRegency->province_id;
                $provinceName = $user->school->cityRegency->province->name ?? null;
            } else {
                $user['school']["province_id"] = null;
            }
        }
        $isProfileComplete = true;
        $missingFields = [];

        if ($user->student) {
            $user->name = $user->student->name;
            if (isset($user->student->school)) {
                $user['student']["school_name"] = $user->student->school->name;
                $user['student']["school_type"] = $user->student->school->type ?? 'school';
                $provinceName = $user->student->school->cityRegency->province->name ?? null;
            } else {
                $user['student']["school_name"] = null;
                $user['student']["school_type"] = null;
            }

            if (empty($user->student->school_id)) {
                $isProfileComplete = false;
                $missingFields[] = 'school_id';
            }
            if (empty($user->student->major_id)) {
                $isProfileComplete = false;
                $missingFields[] = 'major_id';
            }
            if (empty($user->student->phone_number)) {
                $isProfileComplete = false;
                $missingFields[] = 'phone_number';
            }
            if (empty($user->student->address)) {
                $isProfileComplete = false;
                $missingFields[] = 'address';
            }
        } elseif ($user->school) {
            if (empty($user->school->address)) {
                $isProfileComplete = false;
                $missingFields[] = 'address';
            }
            if (empty($user->school->city_regency_id)) {
                $isProfileComplete = false;
                $missingFields[] = 'city_regency_id';
            }
        } elseif ($user->company) {
            if (empty($user->company->address)) {
                $isProfileComplete = false;
                $missingFields[] = 'address';
            }
            if (empty($user->company->city_regency_id)) {
                $isProfileComplete = false;
                $missingFields[] = 'city_regency_id';
            }
        }

        $isUserVerified = false;
        if ($user->role === 'super_admin') {
            $isUserVerified = true;
        } elseif ($user->student) {
            $isUserVerified = (bool) ($user->student->is_verified ?? false);
        } elseif ($user->school) {
            $isUserVerified = (bool) ($user->school->is_verified ?? false);
        } elseif ($user->company) {
            $isUserVerified = (bool) ($user->company->is_verified ?? false);
        }

        $timezone = \App\Support\IndonesianTimezone::resolve($provinceName);
        $user['timezone'] = $timezone['zone'];       // mis. "Asia/Jakarta"
        $user['timezone_label'] = $timezone['label']; // mis. "WIB"
        $user['is_profile_complete'] = $isProfileComplete;
        $user['missing_fields'] = $missingFields;
        $user['is_verified'] = $isUserVerified;

        return response()->json([
            'data' => $user,
        ], 200);
    }

    /**
     * Get current authenticated user's roles and permissions.
     * Used by the frontend to build the permission context after login.
     *
     * GET /api/v1/users/me/permissions
     */
    public function myPermissions(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'role'        => $user->role,
                'roles'       => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 200);
    }

/**
 * @OA\Post(
 *     path="/api/profile",
 *     tags={"User"},
 *     summary="Update profile",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="username",type="string"),
 *                 @OA\Property(property="email",type="string"),
 *                 @OA\Property(property="password",type="string"),
 *                 @OA\Property(property="name",type="string"),
 *                 @OA\Property(property="address",type="string"),
 *                 @OA\Property(property="phone_number",type="string"),
 *                 @OA\Property(property="photo_profile",type="string",format="binary")
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(response=200,description="Profile updated")
 * )
 */
    public function updateProfile(UserUpdateProfileRequest $request, $id = null)
    {

        $data = $request->validated();

        //always fetch fresh from database to ensure relationships are loaded
        if ($id === null) {
            $userId = $request->user()->id;
        } else {
            $userId = $id;
            //user can only update their own profile, or admin can update anyone, or school can update their students
            if ($request->user()->id !== $userId && !$request->user()->tokenCan('admin-access')) {
                // Check if school is trying to update one of their students
                if ($request->user()->tokenCan('school-access')) {
                    $targetUser = User::find($userId);
                    if (!$targetUser || $targetUser->role !== 'student') {
                        throw new HttpResponseException(response([
                            "errors" => "Unauthorized. You can only update your own profile or your school's students."
                        ], 403));
                    }
                    // Verify the student belongs to this school
                    if (!$targetUser->student || $targetUser->student->school_id !== $request->user()->school?->id) {
                        throw new HttpResponseException(response([
                            "errors" => "Unauthorized. This student does not belong to your school."
                        ], 403));
                    }
                } else {
                    throw new HttpResponseException(response([
                        "errors" => "Unauthorized. You can only update your own profile."
                    ], 403));
                }
            }
        }

        $user = User::with(['student', 'school', 'company'])->find($userId);
        if (!$user) {
            throw new HttpResponseException(response([
                "errors" => "User not found."
            ], 404));
        }

        $user->username = $data['username'] ?? $user->username;
        $user->email = $data['email'] ?? $user->email;
        if (isset($data['password']) && !empty($data['password'])) {
            $user->password = $data['password'];
        }

        if (isset($data['email'])) {
            $user->email_verified_at = null;
        }

        if ($request->boolean('reset_photo') || $request->input('reset_photo') === 'true' || $request->input('reset_photo') === '1' || $request->input('reset_photo') === true) {
            if ($user->photo_profile && Storage::disk('public')->exists("photo-profile/{$user->photo_profile}")) {
                Storage::disk('public')->delete("photo-profile/{$user->photo_profile}");
            }
            $user->photo_profile = null;
        } elseif ($request->file('photo_profile')) {
            if ($user->photo_profile && Storage::disk('public')->exists("photo-profile/{$user->photo_profile}")) {
                Storage::disk('public')->delete("photo-profile/{$user->photo_profile}");
            }
            $filename = now()->format('Ymd_His') . '.' . $request->file('photo_profile')->getClientOriginalExtension();
            $user->photo_profile = $filename;
            $request->file('photo_profile')->storeAs('photo-profile', $filename, 'public');
        }

        if ($user->role === 'student') { //updating the previous for what role is updating, refer to previous commit for reference
            $student = $user->student;
            if ($student) {
                $student->name = $data['name'] ?? $student->name;
                $student->address = $data['address'] ?? $student->address;
                $student->phone_number = $data['phone_number'] ?? $student->phone_number;
                if (!empty($student->phone_number)) {
                    $user->whatsapp_number = $student->phone_number;
                }
                $student->school_id = $data['school_id'] ?? $student->school_id;
                $student->date_of_birth = $data['date_of_birth'] ?? $student->date_of_birth;
                $student->gender = $data['gender'] ?? $student->gender;
                $student->class = $data['class'] ?? $student->class;
                $student->skill = $data['skill'] ?? $student->skill;
                $student->portofolio_link = $data['portofolio_link'] ?? $student->portofolio_link;
                $student->social_media_link = $data['social_media_link'] ?? $student->social_media_link;
                $student->major_id = $data['major_id'] ?? $student->major_id;
                // Admin can update is_verified for students
                if (isset($data['is_verified'])) {
                    $student->is_verified = $data['is_verified'];
                }
                $student->save();
                \Log::info('UpdateProfile - Student updated', ['student_id' => $student->id]);
            }
        } else if ($user->role === 'school') {
            $school = $user->school;
            if ($school) {
                $school->name = $data['name'] ?? $school->name;
                $school->address = $data['address'] ?? $school->address;
                $school->phone_number = $data['phone_number'] ?? $school->phone_number;
                $school->website = $data['website'] ?? $school->website;
                $school->npsn = $data['npsn'] ?? $school->npsn;
                $school->accreditation = $data['accreditation'] ?? $school->accreditation;
                $school->status = $data['status'] ?? $school->status;
                $school->description = $data['description'] ?? $school->description;
                $school->city_regency_id = $data['city_regency_id'] ?? $school->city_regency_id;
                // Admin can update is_verified for schools
                if (isset($data['is_verified'])) {
                    $school->is_verified = $data['is_verified'];
                }
                $school->save();
                \Log::info('UpdateProfile - School updated', ['school_id' => $school->id]);
            }
        } else if ($user->role === 'company') {
            $company = $user->company;
            if ($company) {
                $company->name = $data['name'] ?? $company->name;
                $company->address = $data['address'] ?? $company->address;
                $company->phone_number = $data['phone_number'] ?? $company->phone_number;
                $company->city_regency_id = $data['city_regency_id'] ?? $company->city_regency_id;
                $company->sector_id = $data['sector_id'] ?? $company->sector_id;
                $company->description = $data['description'] ?? $company->description;
                $company->website = $data['website'] ?? $company->website;
                // Admin can update is_verified for companies
                if (isset($data['is_verified'])) {
                    $company->is_verified = $data['is_verified'];
                }
                $company->save();
                \Log::info('UpdateProfile - Company updated', ['company_id' => $company->id]);
            }
        }

        $user->save();

        //refresh from database to ensure it returns the actual saved data
        $user = User::with(['student', 'school', 'company'])->find($userId);

        return response()->json([
            'data' => $user,
        ], 200);
    }

    /**
 * @OA\Delete(
 *     path="/api/profile",
 *     tags={"User"},
 *     summary="Delete own profile",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200,description="Profile deleted")
 * )
 */
    public function deleteProfile(Request $request)
    {
        $user = $request->user();

        if ($user->photo_profile) {
            if (Storage::exists("photo-profile/{$user->photo_profile}")) {
                Storage::delete("photo-profile/{$user->photo_profile}");
            }
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ], 200);
    }

    /**
 * @OA\Get(
 *     path="/api/users/count",
 *     tags={"User"},
 *     summary="Dashboard statistics",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200,description="Statistics retrieved")
 * )
 */
    public function count(Request $request)
    {
        $companyCount = User::where('role', 'company')
            ->whereHas("company", function ($query) {
                $query->where("is_verified", true);
            })
            ->count();

        $schoolCount = User::where('role', 'school')
            ->whereHas("school", function ($query) {
                $query->where("is_verified", true);
            })
            ->count();

        $mouCount = Mou::when($request->user()->tokenCan("company-access"), function ($query) use ($request) {
            $query->where("company_id", $request->user()?->company?->id);
        })
            ->when($request->user()->tokenCan("school-access"), function ($query) use ($request) {
                $query->where("school_id", $request->user()?->school?->id);
            })
            ->when($request->user()->tokenCan("student-access"), function ($query) use ($request) {
                $query->where("school_id", $request->user()?->student?->school_id);
            })
            ->where('status', 'accepted')
            ->count();

        $studentQuery = Student::where('school_id', $request->user()?->school?->id)
            ->where('is_verified', true);

        // total semua student
        $studentCount = $studentQuery->count();




        // student tanpa internship
        $totalStudentWithoutInternship = $studentQuery->doesntHave('curriculumVitae.internshipApplications.internship')->count();

        // student dengan internship tapi belum selesai
        $totalStudentInternship = Student::where([
            'school_id' => $request->user()?->school?->id,
            'is_verified' => true,
            'status_magang' => 'ongoing'
        ])->count();

        // student dengan internship selesai
        $totalStudentWithInternship = Student::where([
            'school_id' => $request->user()?->school?->id,
            'is_verified' => true,
            'status_magang' => 'completed'
        ])->count();

        return response()->json([
            'data' => [
                'company_count' => $companyCount,
                'school_count' => $schoolCount,
                'mou_count' => $mouCount,
                'student_count' => $studentCount,
                'total_student_without_internship' => $totalStudentWithoutInternship,
                'total_student_internship' => $totalStudentInternship,
                'total_student_with_internship' => $totalStudentWithInternship,
            ],
        ], 200);
    }

    /**
 * @OA\Get(
 *     path="/api/users/student-summary",
 *     tags={"User"},
 *     summary="Student summary",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200,description="Student summary retrieved")
 * )
 */
    public function studentSummary(Request $request)
    {
        $studentQuery = Student::where('school_id', $request->user()?->school?->id)
            ->where('is_verified', true);

        // total semua student
        $studentCount = $studentQuery->count();

        // student tanpa internship
        $totalStudentWithoutInternship = $studentQuery->doesntHave('curriculumVitae.internshipApplications.internship')->count();

        // student dengan internship tapi belum selesai
        $totalStudentInternship = $studentQuery->whereHas('curriculumVitae.internshipApplications.internship', function ($q) {
            $q->where('is_completed', false);
        })->count();

        // student dengan internship selesai
        $totalStudentWithInternship = $studentQuery->whereHas('curriculumVitae.internshipApplications.internship', function ($q) {
            $q->where('is_completed', true);
        })->count();


        return response()->json([
            'data' => [
                'student_count' => $studentCount,
                'total_student_without_internship' => $totalStudentWithoutInternship,
                'total_student_internship' => $totalStudentInternship,
                'total_student_with_internship' => $totalStudentWithInternship,
            ],
        ], 200);
    }

    /**
 * @OA\Get(
 *     path="/api/email/verify/{id}/{hash}",
 *     tags={"Authentication"},
 *     summary="Verify email",
 *
 *     @OA\Parameter(name="id",in="path",required=true,@OA\Schema(type="string")),
 *     @OA\Parameter(name="hash",in="path",required=true,@OA\Schema(type="string")),
 *
 *     @OA\Response(response=200,description="Email verified"),
 *     @OA\Response(response=403,description="Invalid verification link")
 * )
 */
    public function verifyEmail(Request $request)
    {
        // Middleware "signed" sudah memvalidasi signature & expiry
        $user = User::findOrFail($request->route('id'));

        // Cek hash agar cocok dengan email saat link dibuat
        $expectedHash = sha1($user->getEmailForVerification());
        if (!hash_equals((string) $request->route('hash'), $expectedHash)) {
            throw new HttpResponseException(response([
                "errors" => "Link verifikasi tidak valid."
            ], 403));
        }

        if ($user->hasVerifiedEmail()) {
            throw new HttpResponseException(response([
                "errors" => "Email sudah terverifikasi."
            ], 403));
        }

        // Tandai sebagai terverifikasi + trigger event bawaan
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Email berhasil diverifikasi!']);
    }

    /**
 * @OA\Get(
 *     path="/api/students/import-template",
 *     tags={"Student"},
 *     summary="Download CSV template",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200,description="CSV template downloaded")
 * )
 */
    public function importStudentTemplate(Request $request)
    {
        $path = Storage::path('/import-template/csv-template.csv');

        if (!file_exists($path)) {
            $dir = dirname($path);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, "username,nama,email,password\n");
        }

        return response()->download($path, 'prakerin-siswa-template.csv', [
            'Content-Type' => 'text/csv'
        ]);
    }

    /**
 * @OA\Post(
 *     path="/api/students/import",
 *     tags={"Student"},
 *     summary="Import students from CSV",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"file"},
 *                 @OA\Property(
 *                     property="file",
 *                     type="string",
 *                     format="binary"
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(response=200,description="Import successful"),
 *     @OA\Response(response=400,description="Invalid CSV")
 * )
 */
    public function importStudent(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv|max:1024'
        ]);

        $file = $request->file("file");

        $data = [];


        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ',');

            // Validasi header
            if ($header !== ["username", "nama", "email", "password"]) {
                throw new HttpResponseException(response([
                    "errors" => "File csv yang anda masukkan tidak valid!"
                ], 400));
            }

            // Baca isi file
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }

        if (empty($data)) {
            throw new HttpResponseException(response([
                "errors" => "Data kosong!"
            ], 400));
        }


        DB::transaction(function () use ($data, $request) {
            $users = [];
            $students = [];

            foreach ($data as $row) {
                [$username, $nama, $email, $password] = $row;

                $userId = (string) Str::uuid();
                $studentId = (string) Str::uuid();

                $users[] = [
                    'id' => $userId,
                    'username' => $username,
                    'email' => $email,
                    'role' => 'student',
                    'password' => $password,
                ];

                $students[] = [
                    'id' => $studentId,
                    'user_id' => $userId,
                    'school_id' => $request->user()->school?->id,
                    'name' => $nama,
                    'is_verified' => true,
                ];
            }

            // Bulk insert
            DB::table('users')->insert($users);
            DB::table('students')->insert($students);
        });


        return response()->json([
            'data' => true
        ]);
    }

    /**
     * PATCH /api/v1/users/notification-settings
     * Update the authenticated user's notification preferences.
     */
    public function updateNotificationSettings(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'email_notifications_enabled'    => 'sometimes|boolean',
            'whatsapp_notifications_enabled' => 'sometimes|boolean',
            'whatsapp_number'                => 'sometimes|nullable|string|max:20',
        ]);

        if (array_key_exists('email_notifications_enabled', $validated)) {
            $user->email_notifications_enabled = $validated['email_notifications_enabled'];
        }

        if (array_key_exists('whatsapp_notifications_enabled', $validated)) {
            $user->whatsapp_notifications_enabled = $validated['whatsapp_notifications_enabled'];
        }

        // Automatically sync whatsapp_number from profile phone_number
        $profilePhone = $user->phone_number ?: $user->student?->phone_number ?: $user->school?->phone_number ?: $user->company?->phone_number;
        $user->whatsapp_number = !empty($validated['whatsapp_number']) ? $validated['whatsapp_number'] : ($profilePhone ?: $user->whatsapp_number);

        $user->save();

        return response()->json([
            'message' => 'Notification settings updated successfully',
            'data'    => [
                'email_notifications_enabled'    => (bool) $user->email_notifications_enabled,
                'whatsapp_notifications_enabled' => (bool) $user->whatsapp_notifications_enabled,
                'whatsapp_number'                => $user->whatsapp_number,
                'profile_phone_number'           => $profilePhone,
            ],
        ]);
    }

    // ── AI Logo Fetcher ───────────────────────────────────────────────────────

    /**
     * Inline logo-fetch for universities missing a photo_profile.
     * Processes 5 per request (sync-safe, no queue needed).
     * Each click: Gemini finds URL → download → save → mark verified.
     *
     * POST /api/v1/users/ai-fetch-logos
     */
    public function aiFetchLogos()
    {
        // Grab next university with no logo
        $pending = User::where('role', 'school')
            ->whereNull('photo_profile')
            ->whereHas('school', fn ($q) => $q->where('type', 'university'))
            ->with('school')
            ->limit(1)
            ->get();

        if ($pending->isEmpty()) {
            return response()->json([
                'status'    => 'done',
                'message'   => 'Semua universitas telah diproses!',
                'processed' => 0,
                'succeeded' => 0,
                'remaining' => 0,
                'results'   => [],
            ]);
        }

        $totalRemaining = User::where('role', 'school')
            ->whereNull('photo_profile')
            ->whereHas('school', fn ($q) => $q->where('type', 'university'))
            ->count();

        $results   = [];
        $succeeded = 0;

        foreach ($pending as $user) {
            $name   = $user->school->name ?? $user->username;
            $result = $this->fetchAndSaveLogo($user, $name);
            $results[] = $result;
            if ($result['success']) {
                $succeeded++;
            }
        }

        $remainingAfter = max(0, $totalRemaining - count($pending));

        return response()->json([
            'status'    => 'processed',
            'message'   => "Diproses: " . count($pending) . ", Berhasil: {$succeeded}. Sisa: {$remainingAfter}.",
            'processed' => count($pending),
            'succeeded' => $succeeded,
            'remaining' => $remainingAfter,
            'results'   => $results,
        ]);
    }

    public function resetAiFetchLogosFailed()
    {
        $count = User::where('role', 'school')
            ->where('photo_profile', 'like', 'ai_failed%')
            ->whereHas('school', fn ($q) => $q->where('type', 'university'))
            ->update(['photo_profile' => null]);

        return response()->json([
            'status'  => 'success',
            'message' => "Berhasil mereset {$count} data yang gagal. Siap diproses ulang!",
            'reset'   => $count
        ]);
    }

    private function fetchAndSaveLogo(User $user, string $name): array
    {
        try {
            // Strategy 1: Wikipedia PageImage API
            $candidateUrl = $this->findLogoViaWikipedia($name);

            // Strategy 2: Wikimedia Commons API
            if (!$candidateUrl) {
                $candidateUrl = $this->findLogoViaWikimedia($name);
            }

            // Strategy 3: Domain Clearbit / Favicon
            if (!$candidateUrl) {
                $candidateUrl = $this->findLogoViaDomain($user);
            }

            // Strategy 4: DuckDuckGo Search
            if (!$candidateUrl) {
                $candidateUrl = $this->findLogoViaDuckDuckGo($name);
            }

            // Strategy 5: Gemini AI (if key is set)
            if (!$candidateUrl && config('gemini.api_key')) {
                $candidateUrl = $this->findLogoViaGemini($name);
            }

            if (!$candidateUrl) {
                $reason = 'Tidak ditemukan URL logo yang valid';
                $this->saveFailedAttempt($user, $reason);
                return ['name' => $name, 'success' => false, 'reason' => $reason];
            }

            // Try downloading and saving candidate URL
            return $this->tryDownloadAndSave($user, $name, $candidateUrl);

        } catch (\Exception $e) {
            \Log::error("[AiFetchLogos] ❌ {$name}: " . $e->getMessage());
            $this->saveFailedAttempt($user, $e->getMessage());
            return ['name' => $name, 'success' => false, 'reason' => $e->getMessage()];
        }
    }

    private function findLogoViaWikipedia(string $name): ?string
    {
        try {
            $query = urlencode($name);
            $url   = "https://id.wikipedia.org/w/api.php?action=query&generator=search&gsrsearch={$query}&gsrlimit=3&prop=pageimages&pithumbsize=500&format=json";
            $res   = \Illuminate\Support\Facades\Http::timeout(6)
                ->withHeaders(['User-Agent' => 'PrakerinBot/1.0'])
                ->get($url);

            if ($res->successful()) {
                $pages = $res->json()['query']['pages'] ?? [];
                foreach ($pages as $p) {
                    if (isset($p['thumbnail']['source'])) {
                        $src = $p['thumbnail']['source'];
                        $path = strtolower(parse_url($src, PHP_URL_PATH) ?? '');
                        if (preg_match('/\.(png|jpg|jpeg|svg|webp)/i', $path)) {
                            return $src;
                        }
                    }
                }
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function findLogoViaWikimedia(string $name): ?string
    {
        try {
            $query = urlencode($name . " logo");
            $url   = "https://commons.wikimedia.org/w/api.php?action=query&list=search&srsearch={$query}&srnamespace=6&format=json&srlimit=5";
            $res   = \Illuminate\Support\Facades\Http::timeout(6)
                ->withHeaders(['User-Agent' => 'PrakerinBot/1.0'])
                ->get($url);

            if ($res->successful()) {
                $results = $res->json()['query']['search'] ?? [];
                foreach ($results as $item) {
                    $title = $item['title'] ?? '';
                    if (preg_match('/\.(png|jpg|jpeg|svg|webp)$/i', $title)) {
                        $fileTitle = urlencode($title);
                        $infoUrl   = "https://commons.wikimedia.org/w/api.php?action=query&titles={$fileTitle}&prop=imageinfo&iiprop=url&format=json";
                        $infoRes   = \Illuminate\Support\Facades\Http::timeout(6)
                            ->withHeaders(['User-Agent' => 'PrakerinBot/1.0'])
                            ->get($infoUrl);

                        if ($infoRes->successful()) {
                            $pages = $infoRes->json()['query']['pages'] ?? [];
                            foreach ($pages as $p) {
                                if (isset($p['imageinfo'][0]['url'])) {
                                    return $p['imageinfo'][0]['url'];
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function findLogoViaDomain(User $user): ?string
    {
        try {
            $email = strtolower($user->email ?? '');
            $website = strtolower($user->school->website ?? '');
            $domain = null;

            if ($website) {
                $host = parse_url($website, PHP_URL_HOST) ?? $website;
                $domain = preg_replace('/^www\./', '', $host);
            } elseif (str_contains($email, '@')) {
                $parts = explode('@', $email);
                $d = end($parts);
                if (!in_array($d, ['gmail.com', 'yahoo.com', 'yahoo.co.id', 'hotmail.com', 'outlook.com'])) {
                    $domain = $d;
                }
            }

            if ($domain) {
                $clearbitUrl = "https://logo.clearbit.com/{$domain}";
                $res = \Illuminate\Support\Facades\Http::timeout(4)->get($clearbitUrl);
                if ($res->successful() && strlen($res->body()) > 1024) {
                    return $clearbitUrl;
                }
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function findLogoViaDuckDuckGo(string $name): ?string
    {
        try {
            $q = $name . " logo filetype:png";
            $url = "https://html.duckduckgo.com/html/?q=" . urlencode($q);
            $res = \Illuminate\Support\Facades\Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ])
                ->get($url);

            if ($res->successful()) {
                if (preg_match_all('/uddg=(https%3A%2F%2F[^"&]+\.(?:png|jpg|jpeg|svg|webp))/i', $res->body(), $matches)) {
                    foreach ($matches[1] as $encodedUrl) {
                        $decoded = urldecode($encodedUrl);
                        if (!str_contains($decoded, 'duckduckgo.com') && !str_contains($decoded, 'wikimedia.org')) {
                            return $decoded;
                        }
                    }
                }
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function findLogoViaGemini(string $name): ?string
    {
        try {
            $prompt = <<<PROMPT
Find the official logo image of "{$name}" (an Indonesian university/college).
Return ONLY a direct image URL ending in .png, .jpg, .jpeg, .svg, or .webp.
If you cannot find a reliable URL, return: NONE
No extra text.
PROMPT;

            $result = \Gemini\Laravel\Facades\Gemini::generativeModel('gemini-1.5-flash')
                ->generateContent($prompt);

            $raw = trim($result->text());
            if (!empty($raw) && strtoupper($raw) !== 'NONE' && filter_var($raw, FILTER_VALIDATE_URL)) {
                return $raw;
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function tryDownloadAndSave(User $user, string $name, string $rawUrl): array
    {
        $response = \Illuminate\Support\Facades\Http::timeout(10)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->get($rawUrl);

        if (!$response->successful()) {
            $reason = "Download failed HTTP {$response->status()}";
            $this->saveFailedAttempt($user, $reason);
            return ['name' => $name, 'success' => false, 'reason' => $reason];
        }

        $body        = $response->body();
        $contentType = $response->header('Content-Type') ?? '';

        if (strlen($body) < 1024) {
            $reason = 'Image too small (<1KB)';
            $this->saveFailedAttempt($user, $reason);
            return ['name' => $name, 'success' => false, 'reason' => $reason];
        }

        if (!str_contains($contentType, 'image/')) {
            $reason = "Non-image content-type: {$contentType}";
            $this->saveFailedAttempt($user, $reason);
            return ['name' => $name, 'success' => false, 'reason' => $reason];
        }

        $lower = strtolower(parse_url($rawUrl, PHP_URL_PATH) ?? '');
        $ext = null;
        if (preg_match('/\.(png|jpg|jpeg|svg|webp)$/i', $lower, $m)) {
            $ext = $m[1];
        } else {
            $mimeMap = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
            foreach ($mimeMap as $mime => $e) {
                if (str_contains($contentType, $mime)) { $ext = $e; break; }
            }
        }

        if (!$ext) {
            $ext = 'png';
        }

        $filename = 'ai_logo_' . $user->id . '.' . $ext;
        Storage::disk('public')->put('photo-profile/' . $filename, $body);

        $user->photo_profile = $filename;
        $user->save();

        if ($user->school) {
            $user->school->is_verified = true;
            $user->school->save();
        }

        \Log::info("[AiFetchLogos] ✅ {$name} → {$filename}");
        return ['name' => $name, 'success' => true, 'reason' => $filename];
    }

    private function saveFailedAttempt(User $user, string $reason): void
    {
        $user->photo_profile = \Illuminate\Support\Str::limit('ai_failed: ' . $reason, 250);
        $user->save();
    }

    public function aiFetchLogosStatus()
    {
        $totalUniversities = User::where('role', 'school')
            ->whereHas('school', fn ($q) => $q->where('type', 'university'))
            ->count();

        $pending = User::where('role', 'school')
            ->whereNull('photo_profile')
            ->whereHas('school', fn ($q) => $q->where('type', 'university'))
            ->count();

        $failed = User::where('role', 'school')
            ->where('photo_profile', 'like', 'ai_failed%')
            ->whereHas('school', fn ($q) => $q->where('type', 'university'))
            ->count();

        $done = $totalUniversities - $pending - $failed;

        return response()->json([
            'total'   => $totalUniversities,
            'done'    => $done,
            'failed'  => $failed,
            'pending' => $pending,
        ]);
    }
}
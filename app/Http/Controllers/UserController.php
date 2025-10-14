<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserCreateRequest;
use App\Http\Requests\User\UserLoginRequest;
use App\Http\Requests\User\UserRegisterRequest;
use App\Http\Requests\User\UserUpdateProfileRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Models\Company;
use App\Models\Mou;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Auth;
use DB;
use Http;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
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

        Log::info('Tes'.$isSchool);
        Log::info('Dua '.$request->query('is_school'));
        Log::info('Tiga '.$request->has('is_school'));
        Log::info('Empat'.$isSchool !== null);


        $user = Auth::guard('sanctum')->user();


        $users = User::
            when($user === null, function ($query) use ($search, $isSchool) {
                $query->with(['school']);
                $query->where('role', 'school');
                $query->whereHas('school', function ($q) use ($search, $isSchool) {
                    $q->where('is_verified', true);
                    $q->where('name', 'like', "%$search%");
                    $q->where('type', $isSchool ? 'school' : 'university');
                });
            })
            ->when($user?->tokenCan('school-access') && ($role === 'student'), function ($query, ) use ($search, $isVerified, $user, $status) {
                $query->with(
                    'student.curriculumVitae.internshipApplications.jobOpening.company.user',
                    'student.curriculumVitae.internshipApplications.jobOpening.company.cityRegency.province'
                );
                $query->where('role', 'student');
                $query->whereHas('student', function ($q) use ($search, $isVerified, $user) {
                    $q->where('is_verified', $isVerified);
                    $q->where('name', 'like', "%$search%");
                    $q->where('school_id', $user->school->id);
                });
                $query->when(in_array($status, ['ongoing', 'completed', 'not_started']), function ($query) use ($status) {
                    $query->whereHas('student', function ($q) use ($status) {
                        $q->where('status', $status);
                    });
                });
            })
            ->when(($user?->tokenCan('school-access') || $user?->tokenCan('student-access')) && ($role === 'company'), function ($query) use ($search, $isMou, $user) {
                $query->with(['company.cityRegency.province', 'company.mous']);
                $query->where('role', 'company');
                $query->whereHas('company', function ($q) use ($search, $isMou, $user) {
                    $q->where('is_verified', true);
                    $q->where('name', 'like', "%$search%");

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
            ->when($user?->tokenCan('admin-access'), function ($query) use ($role, $request, $isSchool) {
                $isVerified = $request->has('is_verified')
                    ? filter_var($request->query('is_verified'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    : null;


                $query->with(['student', 'school', 'company']);
                $query->when($role, function ($query, $role) {
                    return $query->where('role', $role);
                });
                $query->when($isVerified !== null, function ($query) use ($isVerified, $isSchool) {
                    $query->where(function ($q) use ($isVerified, $isSchool) {
                        $q->whereHas('student', fn($q2) => $q2->where('is_verified', $isVerified))
                            ->orWhereHas('school', function($q2) use ($isVerified, $isSchool) {
                                $q2->where('is_verified', $isVerified);
                                $q2->when($isSchool !== null, fn($q3) => $q3->where('type', $isSchool ? 'school' : 'university'));
                            })
                            ->orWhereHas('company', fn($q2) => $q2->where('is_verified', $isVerified));
                    });
                });
            })
            ->orderBy('updated_at', 'desc')
            ->paginate($limit)
            ->through(function ($item) use ($user, $role) {
                if ($user === null) {
                    return [
                        'id' => $item->school->id,
                        'name' => $item->school->name,
                    ];
                } else if (($user?->tokenCan('school-access') && ($role === 'student'))) {

                    return [
                        'id' => $item->id,
                        'username' => $item->username,
                        'email' => $item->email,
                        'role' => $item->role,
                        'photo_profile' => $item->photo_profile,
                        'student' => [
                            'id' => $item->student?->id,
                            'name' => $item->student?->name,
                            'class' => $item->student?->class,
                            'company' => $item->student?->curriculumVitae
                                ->flatMap->internshipApplications
                                ->map->jobOpening
                                ->map->company
                                ->unique('id')
                                ->map(function ($company) {
                                    $data = $company->toArray();
                                    $data['item'] = $company->item?->toArray();
                                    $data['city_regency'] = $company->cityRegency?->toArray();
                                    $data['province'] = $company->cityRegency?->province?->toArray();
                                    return $data;
                                })
                                ->values(),
                        ],
                        'major' => [
                            'name' => $item->student->major?->name
                        ],
                        'status' => $item->student->status, // ✅ tambahin status magang
                    ];
                } else if (($user?->tokenCan('school-access') || $user?->tokenCan('student-access')) && ($role === 'company')) {
                    return [
                        'id' => $item->id,
                        'username' => $item->username,
                        'email' => $item->email,
                        'role' => $item->role,
                        'photo_profile' => $item->photo_profile,
                        'company' => $item->company->makeHidden(['cityRegency', 'mous']),
                        'city_regency' => $item->company->cityRegency->makeHidden(['province']),
                        'province' => $item->company->cityRegency->province,
                        'mou' => !$item->company->mous->where('status', 'accepted')->isEmpty(),
                    ];
                } else if ($user?->tokenCan('company-access') && ($role === 'school')) {
                    return [
                        'id' => $item->id,
                        'username' => $item->username,
                        'email' => $item->email,
                        'role' => $item->role,
                        'photo_profile' => $item->photo_profile,
                        'name' => $item->school->name,
                        'school' => $item->school->makeHidden(['mous', 'cityRegency']),
                        'mou' => !$item->school->mous->where('status', 'accepted')->isEmpty(),
                        'city_regency' => $item->school->cityRegency->makeHidden(['province']),
                        'province' => $item->school->cityRegency->province
                    ];
                } else if ($user?->tokenCan('company-access') && ($role === 'student')) {
                    return [
                        'id' => $item->id,
                        'username' => $item->username,
                        'email' => $item->email,
                        'phone_number' => $item->phone_number,
                        'photo_profile' => $item->photo_profile,
                        'student' => $item->student,
                        'school' => $item->student->school,
                        'internship' => $item->student->internships->first(),
                        'field' => $item->student->internships->first()?->internshipApplication->jobOpening->field?->name ?? null
                    ];
                }

                return $item;


            });


        return response()->json($users, 200);

    }

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
            }
            $student->user_id = $user->id;
            $student->save();

        } else if ($user->role === "school") {
            $school = new School();
            $school->name = $data['name'];
            $school->address = $data['address'];
            $school->user_id = $user->id;
            $school->save();

        } else if ($user->role === "company") {
            $company = new Company();
            $company->name = $data['name'];
            $company->address = $data['address'];
            $company->user_id = $user->id;
            $company->save();

        }


        return response()->json([
            'data' => $user->load('student', 'school', 'company')
        ], 201);
    }
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



        if (!$user->student) {
            $user->makeHidden('student');
        }
        if (!$user->school) {
            $user->makeHidden('school');
        }
        if (!$user->company) {
            $user->makeHidden('company');

        }

        if ($user->role === 'company') {

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
                'email' => $user->email,
                'photo_profile' => $user->photo_profile,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'company' => $user->company->makeHidden(['cityRegency', 'sector', 'jobOpenings']),
                'city_regency' => $user->company->cityRegency->makeHidden(['province']),
                'province' => $user->company->cityRegency->province,
                'sector' => $user->company->sector,
                'job_openings' => $user->company->jobOpenings,
                'mou' => $mou

            ];
        } else if ($user->role === 'school') {

            $user->school->load([
                'cityRegency.province',
            ]);

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
                'school' => $user->school->makeHidden(['cityRegency',]),
                'city_regency' => $user->school->cityRegency->makeHidden(['province']),
                'province' => $user->school->cityRegency->province,
                'mou' => $mou,
            ];
        } else if ($user->role === 'student') {
            if ($user->student->status === 'ongoing' && isset($request->user()->company->id)) {
                $user->student->load([
                    'internships' => function ($q) use ($request) {
                        $q->where('is_completed', false)
                            ->where('company_id', $request->user()->company->id)
                            ->with('company.user', 'company.cityRegency.province');
                    },
                ], 'internships.internshipApplication.jobOpening.field');

                $user = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'photo_profile' => $user->photo_profile,
                    'student' => $user->student,
                    'company' => $user->student->internships->first()?->company?->makeHidden(['cityRegency', 'mous']),
                    'city_regency' => $user->student->internships->first()?->company?->cityRegency?->makeHidden(['province']),
                    'province' => $user->student->internships->first()?->company?->cityRegency?->province,
                    'internship' => $user->student->internships->first(),
                    'field' => $user->student->internships->first()?->internshipApplication->jobOpening->field?->name ?? null,
                    'tipe' => $user->student->internships->first()?->internshipApplication->jobOpening->type ?? null,
                ];
            } else {
                $user->student->load([
                    'major',
                ]);
            }
        }

        return response()->json([
            'data' => $user,
        ], 200);
    }
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
    public function destroy(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            throw new HttpResponseException(response([
                "errors" => "User not found."
            ], 404));
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
    public function register(UserRegisterRequest $request, User $user)
    {
        $data = $request->validated();

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
        $user->sendEmailVerificationNotification();


        $token = null;

        if ($user->role === "student") {
            $student = new Student();
            $student->name = $data['name'];
            $student->school_id = $data['school_id'];
            $student->user_id = $user->id;
            $student->save();
            $token = $user->createToken('Auth Token', ['student-access'])->plainTextToken;

        } else if ($user->role === "school") {
            $school = new School();
            $school->name = $data['name'];
            $school->address = $data['address'];
            $school->user_id = $user->id;
            $school->save();
            $token = $user->createToken('Auth Token', ['school-access'])->plainTextToken;

        } else if ($user->role === "company") {
            $company = new Company();
            $company->name = $data['name'];
            $company->address = $data['address'];
            $company->user_id = $user->id;
            $company->save();
            $token = $user->createToken('Auth Token', ['company-access'])->plainTextToken;

        }

        if (!$token) {
            throw new HttpResponseException(response([
                "errors" => "Failed to create token"
            ], 500));
        }

        return response()->json(['token' => $token, 'role' => $user->role], 201);
    }
    public function login(UserLoginRequest $request)
    {
        $data = $request->validated();

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

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new HttpResponseException(response([
                "errors" => "Email atau password salah!"
            ], 400));
        }

        $token = null;


        if ($user->role === "student") {
            $token = $user->createToken('Auth Token', ['student-access'])->plainTextToken;
        } else if ($user->role === "school") {
            $token = $user->createToken('Auth Token', ['school-access'])->plainTextToken;
        } else if ($user->role === "company") {
            $token = $user->createToken('Auth Token', ['company-access'])->plainTextToken;
        } else if ($user->role === "super_admin") {
            $token = $user->createToken('Auth Token', ['admin-access'])->plainTextToken;
        }

        if (!$token) {
            throw new HttpResponseException(response([
                "errors" => "Failed to create token"
            ], 500));
        }

        return response()->json(['token' => $token, 'role' => $user->role], 200);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout success'], 200);
    }
    public function profile(Request $request)
    {
        $user = $request->user()->with(['student', 'school', 'company'])->find($request->user()->id);


        if (!$user->student) {
            $user->makeHidden('student');
        }
        if (!$user->school) {
            $user->makeHidden('school');
        }
        if (!$user->company) {
            $user->makeHidden('company');
        }

        if ($user->company) {
            $user->name = $user->company->name;
            if (isset($user->company->cityRegency)) {
                $user['company']["province_id"] = $user->company->cityRegency->province_id;
            } else {
                $user['company']["province_id"] = null;
            }
        }
        if ($user->school) {
            $user->name = $user->school->name;
            if (isset($user->school->cityRegency)) {
                $user['school']["province_id"] = $user->school->cityRegency->province_id;
            } else {
                $user['school']["province_id"] = null;
            }
        }
        if ($user->student) {
            $user->name = $user->student->name;
            if (isset($user->student->school)) {
                $user['student']["school_name"] = $user->student->school->name;
            } else {
                $user['student']["school_name"] = null;
            }

        }

        return response()->json([
            'data' => $user,
        ], 200);
    }
    public function updateProfile(UserUpdateProfileRequest $request)
    {

        $data = $request->validated();


        $user = $request->user();

        $user->username = $data['username'] ?? $user->username;
        $user->email = $data['email'] ?? $user->email;
        $user->password = $data['password'] ?? $user->password;

        if (isset($data['email'])) {
            $user->email_verified_at = null;
        }



        if ($request->file('photo_profile')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('photo_profile')->getClientOriginalExtension();
            $user->photo_profile = $filename;
            $request->file('photo_profile')->storeAs('photo-profile', $filename, 'public');
        }



        if ($user->tokenCan('student-access')) {
            $student = $user->student;
            $student->name = $data['name'] ?? $student->name;
            $student->address = $data['address'] ?? $student->address;
            $student->phone_number = $data['phone_number'] ?? $student->phone_number;
            $student->school_id = $data['school_id'] ?? $student->school_id;
            $student->date_of_birth = $data['date_of_birth'] ?? $student->date_of_birth;
            $student->gender = $data['gender'] ?? $student->gender;
            $student->class = $data['class'] ?? $student->class;
            $student->skill = $data['skill'] ?? $student->skill;
            $student->portofolio_link = $data['portofolio_link'] ?? $student->portofolio_link;
            $student->social_media_link = $data['social_media_link'] ?? $student->social_media_link;
            $student->major_id = $data['major_id'] ?? $student->major_id;
            $student->save();
        } else if ($user->tokenCan('school-access')) {

            $school = $user->school;
            $school->name = $data['name'] ?? $school->name;
            $school->address = $data['address'] ?? $school->address;
            $school->phone_number = $data['phone_number'] ?? $school->phone_number;
            $school->website = $data['website'] ?? $school->website;
            $school->npsn = $data['npsn'] ?? $school->npsn;
            $school->accreditation = $data['accreditation'] ?? $school->accreditation;
            $school->status = $data['status'] ?? $school->status;
            $school->description = $data['description'] ?? $school->description;
            $school->city_regency_id = $data['city_regency_id'] ?? $school->city_regency_id;


            $school->save();
        } else if ($user->tokenCan('company-access')) {
            $company = $user->company;
            $company->name = $data['name'] ?? $company->name;
            $company->address = $data['address'] ?? $company->address;
            $company->phone_number = $data['phone_number'] ?? $company->phone_number;
            $company->city_regency_id = $data['city_regency_id'] ?? $company->city_regency_id;
            $company->sector_id = $data['sector_id'] ?? $company->sector_id;
            $company->description = $data['description'] ?? $company->description;
            $company->website = $data['website'] ?? $company->website;

            $company->save();
        }


        $user->save();


        return response()->json([
            'data' => $user->load(['student', 'school', 'company']),
        ], 200);
    }
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

        $mouCount = Mou::
            when($request->user()->tokenCan("company-access"), function ($query) use ($request) {
                $query->where("company_id", $request->user()->company->id);
            })
            ->when($request->user()->tokenCan("school-access"), function ($query) use ($request) {
                $query->where("school_id", $request->user()->school->id);
            })
            ->when($request->user()->tokenCan("student-access"), function ($query) use ($request) {
                $query->where("school_id", $request->user()->student->school_id);
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
            'status' => 'ongoing'
        ])->count();

        // student dengan internship selesai
        $totalStudentWithInternship = Student::where([
            'school_id' => $request->user()?->school?->id,
            'is_verified' => true,
            'status' => 'completed'
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
    public function importStudentTemplate(Request $request)
    {
        $path = Storage::path('/import-template/csv-template.csv');

        return response()->download($path, 'csv-template.csv', [
            'Content-Type' => 'text/csv'
        ]);
    }
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
                    'school_id' => $request->user()->school->id,
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
}

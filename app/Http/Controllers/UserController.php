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
use Http;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $isVerified = filter_var($request->query('is_verified', true), FILTER_VALIDATE_BOOLEAN);
        $search = $request->query('search', '');
        $limit = $request->query('limit', 10);
        $role = $request->query('role', null);

        if (!Auth::guard('sanctum')->user()) {
            $users = User::with(['school'])
                ->where('role', 'school')
                ->whereHas('school', function ($q) use ($search) {
                    $q->where('is_verified', true);
                    $q->where('name', 'like', "%$search%");
                })
                ->paginate($limit);

            $users->getCollection()->transform(function ($item) {

                return [
                    'id' => $item->school->id,
                    'name' => $item->school->name,
                ];
            });

            return response()->json($users, 200);
        }

        $user = Auth::guard('sanctum')->user();

        if ($user->tokenCan('school-access') && ($role === 'student')) {

            $status = $request->query('status', null);

            $users = User::with(
                'student.curriculumVitae.internshipApplications.jobOpening.company.user',
                'student.curriculumVitae.internshipApplications.jobOpening.company.cityRegency.province'
            )
                ->where('role', 'student')
                ->whereHas('student', function ($q) use ($search, $isVerified, $user) {
                    $q->where('is_verified', $isVerified);
                    $q->where('name', 'like', "%$search%");
                    $q->where('school_id', $user->school->id);
                })
                ->when(in_array($status, ['ongoing', 'completed', 'not_started']), function ($query) use ($status) {
                    $query->whereHas('student', function ($q) use ($status) {
                        $q->where('status', $status);
                    });
                })
                ->paginate($limit);


            $users->getCollection()->transform(function ($user) use ($isVerified) {

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'photo_profile' => $user->photo_profile,
                    'student' => [
                        'id' => $user->student?->id,
                        'name' => $user->student?->name,
                        'class' => $user->student?->class,
                        'company' => $user->student?->curriculumVitae
                            ->flatMap->internshipApplications
                            ->map->jobOpening
                            ->map->company
                            ->unique('id')
                            ->map(function ($company) {
                                $data = $company->toArray();
                                $data['user'] = $company->user?->toArray();
                                $data['city_regency'] = $company->cityRegency?->toArray();
                                $data['province'] = $company->cityRegency?->province?->toArray();
                                return $data;
                            })
                            ->values(),
                    ],
                    'major' => [
                        'name' => $user->student->major?->name
                    ],
                    'status' => $user->student->status, // ✅ tambahin status magang
                ];
            });



            return response()->json($users, 200);
        } else if (($user->tokenCan('school-access') || $user->tokenCan('student-access')) && ($role === 'company')) {

            $isMou = $request->has('is_mou')
                ? filter_var($request->query('is_mou'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;

            $users = User::with(['company.cityRegency.province', 'company.mous'])
                ->where('role', 'company')
                ->whereHas('company', function ($q) use ($search, $isMou, $user) {
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
                })
                ->paginate($limit);

            $users->getCollection()->transform(function ($item) {

                return [
                    'id' => $item->id,
                    'username' => $item->username,
                    'email' => $item->email,
                    'role' => $item->role,
                    'photo_profile' => $item->photo_profile,
                    'company' => $item->company->makeHidden(['cityRegency', 'mous']),
                    'city_regency' => $item->company->cityRegency->makeHidden(['province']),
                    'province' => $item->company->cityRegency->province,
                    'mou' => $item->company->mous->isEmpty() ? false : true,
                ];
            });

            return response()->json($users, 200);

        } else if ($user->tokenCan('company-access', ) && ($role === 'school')) {

            $isMou = $request->has('is_mou')
                ? filter_var($request->query('is_mou'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;

            $users = User::with(['school.mous', 'school.cityRegency.province'])
                ->where('role', 'school')
                ->whereHas('school', function ($q) use ($search, $isMou, $user) {
                    $q->where('is_verified', true);
                    $q->where('name', 'like', "%$search%");

                    if ($isMou === true) {
                        $q->whereHas('mous', function ($q2) use ($user) {
                            $q2->where('company_id', $user->company->id)
                                ->where('status', 'active');
                        });
                    } elseif ($isMou === false) {
                        $q->whereDoesntHave('mous', function ($q2) use ($user) {
                            $q2->where('company_id', $user->company->id)
                                ->where('status', 'active');
                        });
                    }
                })
                ->paginate($limit);

            $users->getCollection()->transform(function ($item) {

                return [
                    'id' => $item->id,
                    'username' => $item->username,
                    'email' => $item->email,
                    'role' => $item->role,
                    'photo_profile' => $item->photo_profile,
                    'name' => $item->school->name,
                    'school' => $item->school->makeHidden(['mous', 'cityRegency']),
                    'mou' => $item->school->mous->isEmpty() ? false : true,
                    'city_regency' => $item->school->cityRegency->makeHidden(['province']),
                    'province' => $item->school->cityRegency->province
                ];
            });

            return response()->json($users, 200);

        } else if ($user->tokenCan('company-access') && ($role === 'student')) {



            $users = User::with(['student.school', 'student.internships'])
                ->where('role', 'student')
                ->whereHas('student.internships', function ($query) use ($user) {
                    $query->where('company_id', $user->company->id);
                    $query->where('is_completed', false);
                })

                ->paginate($limit);

            $users->getCollection()->transform(function ($item) {

                return [
                    'id' => $item->id,
                    'username' => $item->username,
                    'email' => $item->email,
                    'phone_number' => $item->phone_number,
                    'photo_profile' => $item->photo_profile,
                    'student' => $item->student,
                    'school' => $item->student->school,
                    'internship' => $item->student->internships->first()
                ];
            });
            return response()->json($users, 200);

        } else if ($user->tokenCan('admin-access')) {
            $users = User::with(['student', 'school', 'company'])
                ->when($role, function ($query, $role) {
                    return $query->where('role', $role);
                })
                ->when(isset($isVerified), function ($query) use ($isVerified) {
                    $query->where(function ($q) use ($isVerified) {
                        $q->whereHas('student', fn($q2) => $q2->where('is_verified', $isVerified))
                            ->orWhereHas('school', fn($q2) => $q2->where('is_verified', $isVerified))
                            ->orWhereHas('company', fn($q2) => $q2->where('is_verified', $isVerified));
                    });
                })
                ->paginate($limit);

            return response()->json($users, 200);
        } else {
            throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 403));
        }
    }

    /**
     * Store a newly created resource in storage.
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
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
                'job_openings' => $user->company->jobOpenings
            ];
        } else if ($user->role === 'school') {

            $user->school->load([
                'cityRegency.province',
            ]);

            $user = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'photo_profile' => $user->photo_profile,
                'school' => $user->school->makeHidden(['cityRegency',]),
                'city_regency' => $user->school->cityRegency->makeHidden(['province']),
                'province' => $user->school->cityRegency->province,
            ];
        } else if ($user->role === 'student') {
            $user->student->load([
                'major',
            ]);
        }

        return response()->json([
            'data' => $user,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
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
                $user->student->is_verified = false;
                $user->student->save();

                return response()->json([
                    'data' => true,
                ], 200);
            }
        }

        $user->username = $data['username'] ?? $user->username;
        $user->email = $data['email'] ?? $user->email;
        $user->password = $data['password'] ?? $user->password;

        if (!$data['email']) {
            $user->email_verified_at = null;
        }

        if ($request->file('image')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('image')->getClientOriginalExtension();
            $user->photo_profile = $filename;
            $request->file('image')->storeAs('photo-profile', $filename, 'public');
        }


        if ($user->role === 'student') {
            $student = $user->student;
            $student->name = $data['name'] ?? $student->name;
            $student->address = $data['address'] ?? $student->address;
            $student->phone_number = $data['phone_number'] ?? $student->phone_number;
            $student->name = $data['name'] ?? $student->name;
            $student->school_id = $data['school_id'] ?? $student->school_id;
            $student->date_of_birth = $data['date_of_birth'] ?? $student->date_of_birth;
            $student->save();
        } else if ($user->role === 'school') {
            $school = $user->school;
            $school->name = $data['name'] ?? $school->name;
            $school->address = $data['address'] ?? $school->address;
            $school->phone_number = $data['phone_number'] ?? $school->phone_number;
            $school->website = $data['website'] ?? $school->website;
            $school->npsn = $data['npsn'] ?? $school->npsn;
            $school->accreditation = $data['accreditation'] ?? $school->accreditation;
            $school->status = $data['status'] ?? $school->status;
            $school->save();
        } else if ($user->role === 'company') {
            $company = $user->company;
            $company->name = $data['name'] ?? $company->name;
            $company->address = $data['address'] ?? $company->address;
            $company->city_regency_id = $data['city_regency_id'] ?? $company->city_regency_id;
            $company->sector_id = $data['sector_id'] ?? $company->sector_id;
            $company->save();
        }

        $user->save();

        return response()->json([
            'data' => $user->load(['student', 'school', 'company']),
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     */
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
        }
        if ($user->school) {
            $user->name = $user->school->name;
        }
        if ($user->student) {
            $user->name = $user->student->name;
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
            ->where('status', 'active')
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

}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Models\Company;
use App\Models\ProfileImage;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'data' => $user,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function register(UserRegisterRequest $request, User $user)
    {
        $data = $request->validated();

        // $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
        //     'secret' => config('services.nocaptcha.secret'),
        //     'response' => $data['recaptcha_token'],
        // ]);

        // if (!$response->json('success')) {
        //     throw new HttpResponseException(response([
        //         "errors" => [
        //             "message" => "Captcha failed"
        //         ]
        //     ], 422));
        // }

        $user = new User();
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->password = Hash::make($data['password']);
        $user->save();

        if ($request->file('image')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('image')->getClientOriginalExtension();
            ProfileImage::create([
                'image' => $filename,
                'user_id' => $user->id
            ]);
            $request->file('image')->storeAs('profile', $filename);
        }

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

        } else if ($user->role === "industry") {
            $company = new Company();
            $company->name = $data['name'];
            $company->address = $data['address'];
            $company->user_id = $user->id;
            $company->save();
            $token = $user->createToken('Auth Token', ['industry-access'])->plainTextToken;

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

        // $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
        //     'secret' => config('services.nocaptcha.secret'),
        //     'response' => $data['recaptcha_token'],
        // ]);

        // if (!$response->json('success')) {
        //     throw new HttpResponseException(response([
        //         "errors" => [
        //             "message" => "Captcha failed"
        //         ]
        //     ], 422));
        // }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new HttpResponseException(response([
                "errors" => "Email or password wrong!"
            ], 401));
        }

        $token = null;


        if ($user->role === "student") {
            $token = $user->createToken('Auth Token', ['student-access'])->plainTextToken;
        } else if ($user->role === "school") {
            $token = $user->createToken('Auth Token', ['school-access'])->plainTextToken;
        } else if ($user->role === "industry") {
            $token = $user->createToken('Auth Token', ['industry-access'])->plainTextToken;
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
}

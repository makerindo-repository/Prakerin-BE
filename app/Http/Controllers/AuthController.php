<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ProfileImage;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\error;
use function Symfony\Component\Clock\now;

class AuthController extends Controller
{
    public function unauthorized()
    {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * @OA\Get(
     *  path="/login",
     *  tags={"Auth"},
     *  summary="Login user",
     *  @OA\Response(
     *      response=200,
     *      description="Successful login",
     *  )
     *)
     */

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'recaptcha_token' => 'required',
            'image' => 'image'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
        //     'secret' => config('services.nocaptcha.secret'),
        //     'response' => $request->recaptcha_token,
        // ]);

        // if (!$response->json('success')) {
        //     return response()->json(['message' => 'Captcha failed', "data" => $request], 422);
        // }

        if (Auth::attempt($request->only('email', 'password'))) {
            if (Auth::user()->role === "student") {
                $token = Auth::user()->createToken('Auth Token', ['student:access'])->plainTextToken;
                return response()->json(['token' => $token, "role" => 'student'], 200);
            }
            if (Auth::user()->role === "school") {
                $token = Auth::user()->createToken('Auth Token', ['school:access'])->plainTextToken;
                return response()->json(['token' => $token, "role" => 'school'], 200);
            }
            if (Auth::user()->role === "industry") {
                $token = Auth::user()->createToken('Auth Token', ['industry:access'])->plainTextToken;
                return response()->json(['token' => $token, "role" => 'industry'], 200);
            }
            $token = Auth::user()->createToken('Auth Token', ['admin:access'])->plainTextToken;

            return response()->json(['token' => $token], 200);
        }

        return response()->json(['error' => 'unauthorized'], 401);
    }

    public function register(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:users,username|regex:/^[a-zA-Z0-9._]+$/u',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|confirmed',
            'role' => 'required|in:student,school,industry,super_admin',
            'recaptcha_token' => 'required'
        ]);

        $userData = $request->only('username', 'email', 'password', 'role');
        $model = null;
        $data = [];

        if ($request->role == 'student') {
            $data = $request->only('name', 'date_of_birth', 'gender', 'phone_number', 'address', 'school_id', 'user_id');
            $model = Student::class;
            $validator->addRules([
                'name' => 'required|string|max:255',
                // 'date_of_birth' =>  'required|date',
                // 'phone_number' => 'required|numeric',
                // 'address' => 'required|string',
                'school_id' => 'required|max:20',
                // 'user_id' => 'required|max:20',
                // 'gender' => 'required|in:female,male',
            ]);
        } else if ($request->role == 'school' || $request->role == 'industry') {
            $request->role == 'school' ? $model = School::class : $model = Company::class;
            $request->role == 'school' ? $data = $request->only('name', 'address', 'phone_number', 'user_id', )
                : $data = $request->only('name', 'address', 'phone_number', 'user_id', 'sector');
            $validator->addRules([
                'name' => 'required|string|max:255',
                // 'phone_number' => 'required|numeric',
                'address' => 'required|string',
                // 'user_id' => 'required|max:20',
                'is_verified' => 'boolean',
            ]);

            // $request->role == 'industry' ? $validator->addRules([
            //     'sector' => 'required'
            // ]) :  null;
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.nocaptcha.secret'),
            'response' => $request->recaptcha_token,
        ]);

        if (!$response->json('success')) {
            // return response()->json(['message' => 'Captcha failed', "data" => $request->file('image')], 422);
            dd($request);
        }

        try {
            $userCreate = $user::create($userData);

            $data['user_id'] = $userCreate->id;
            $data['date_of_birth'] = "2000/1/2";
            $create = $model::create($data);
            $create;
            if ($request->file('image')) {
                $filename = now()->format('Ymd_His') . '.' . $request->file('image')->getClientOriginalExtension();
                ProfileImage::create([
                    'image' => $filename,
                    'user_id' => $create->id
                ]);
                $request->file('image')->storeAs('profile', $filename);
            }
            // dd($request);
            if (Auth::attempt($request->only('email', 'password'))) {
                if (Auth::user()->role === "student") {
                    $token = Auth::user()->createToken('Auth Token', ['student:access'])->plainTextToken;
                    return response()->json(['token' => $token, "role" => 'student'], 200);
                }
                if (Auth::user()->role === "school") {
                    $token = Auth::user()->createToken('Auth Token', ['school:access'])->plainTextToken;
                    return response()->json(['token' => $token, "role" => 'school'], 200);
                }
                if (Auth::user()->role === "industry") {
                    $token = Auth::user()->createToken('Auth Token', ['industry:access'])->plainTextToken;
                    return response()->json(['token' => $token, "role" => 'industry'], 200);
                }
                $token = Auth::user()->createToken('Auth Token', ['admin:access'])->plainTextToken;

                return response()->json(['token' => $token], 200);
            }
        } catch (\Throwable $th) {
            return response()->json(['error' => 'failed to Register', 'message' => $th], 422);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout success'], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Student\StudentCreateRequest;
use App\Http\Requests\Student\StudentUpdateRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Models\ProfileImage;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StudentCreateRequest $request)
    {

        $data = $request->validated();

        $user = new User();
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->role = 'student';
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

        $student = new Student();
        $student->name = $data['name'];

        if ($request->user()->role === 'school') {
            $student->school_id = $request->user()->school->id;
        } else {
            $student->school_id = $data['school_id'];
        }

        $student->user_id = $user->id;
        $student->save();



        return response()->json([
            'data' => $student,
        ], 201);

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
    public function update(int $id, StudentUpdateRequest $request, )
    {

        dd(
            $request,
        );
        $student = Student::find($id);

        if (!$student) {
            throw new HttpResponseException(response([
                "errors" => "Student not found"
            ], 404));
        }


        // $data = $request->validated();
        dd($request->all());


        return response()->json([
            'message' => 'Student updated successfully',
            'a' => $request,
            // 'data' => $data,
            'w' => $student
        ], 200);

        // dd($data);


    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

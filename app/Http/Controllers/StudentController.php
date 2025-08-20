<?php

namespace App\Http\Controllers;

use App\Http\Requests\Student\StudentCreateRequest;
use App\Http\Requests\Student\StudentUpdateRequest;
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
    public function index(Request $request)
    {
        // dd(Student::where('school_id', $request->user()->school->id)->get());
        $limit = $request->query('limit', 10);
        $search = $request->query('search', '');
        $isAccepted = filter_var($request->query('is_accepted', true), FILTER_VALIDATE_BOOLEAN);

        $students = [];
        if ($request->user()->tokenCan('school-access')) {
            $students = Student::where('school_id', $request->user()->school->id)
                ->where('is_accepted', $isAccepted)
                ->where('name', 'like', "%$search%")
                ->paginate($limit);
        } else {
            $students = Student::where('name', 'like', "%$search%")
                ->where('is_accepted', $isAccepted)
                ->paginate($limit);
        }

        return response()->json($students, 200);
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

        if ($request->file('image')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('image')->getClientOriginalExtension();
            $user->photo_profile = $filename;
            $request->file('image')->storeAs('profile', $filename);
        }
        $user->save();


        $student = new Student();
        $student->name = $data['name'];

        if ($request->user()->tokenCan('school-access')) {
            $student->school_id = $request->user()->school->id;
            $student->is_accepted = true;
        } else {
            $student->school_id = $data['school_id'];
        }
        $student->user_id = $user->id;
        $student->save();

        return response()->json([
            'data' => $student->load(['user.profileImage']),
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request)
    {
        $student = Student::with(['user.profileImage'])->find($id);

        if (!$student) {
            throw new HttpResponseException(response([
                "errors" => "Student not found."
            ], 404));
        }

        if ($request->user()->tokenCan('school:access') && $student->school_id !== $request->user()->school->id) {
            throw new HttpResponseException(response([
                "errors" => "Forbidden."
            ], 403));
        }

        return response()->json([
            'data' => $student,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id, StudentUpdateRequest $request, )
    {
        $student = Student::find($id);

        if (!$student) {
            throw new HttpResponseException(response([
                "errors" => "Student not found."
            ], 404));
        }


        $data = $request->validated();


        return response()->json([
            'message' => 'Student updated successfully',
            'a' => $request,
            'data' => $data,
            'w' => $student
        ], 200);


    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $student = Student::find($id);

        if (!$student) {
            throw new HttpResponseException(response([
                "errors" => "Student not found"
            ], 404));
        }

        $student->delete();

        return response()->json([
            'message' => 'OK',
        ], 200);
    }

    public function studentCount(Request $request)
    {

        $studentCount = Student::where('school_id', $request->user()->school->id)
            ->where('is_verified', true)
            ->count();
        return response()->json([
            'data' => $studentCount
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Requests\Student\StudentCreateRequest;
use App\Http\Requests\Student\StudentUpdateRequest;
use App\Models\ProfileImage;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/student',
    summary: 'Get student list',
    tags: ['Student'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Search student name',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Pagination limit',
            schema: new OA\Schema(type: 'integer', default: 10)
        ),
        new OA\Parameter(
            name: 'is_verified',
            in: 'query',
            required: false,
            description: 'Filter verified students',
            schema: new OA\Schema(type: 'boolean', default: true)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Student list retrieved successfully'
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthorized'
        )
    ]
)]
    public function index(Request $request)
    {
        // dd(Student::where('school_id', $request->user()->school->id)->get());
        $limit = $request->query('limit', 10);
        $search = $request->query('search', '');
        $isAccepted = filter_var($request->query('is_verified', true), FILTER_VALIDATE_BOOLEAN);

        $students = [];
        if ($request->user()->tokenCan('school-access')) {
            $students = Student::where('school_id', $request->user()->school->id)
                ->where('is_verified', $isAccepted)
                ->where('name', 'like', "%$search%")
                ->paginate($limit);
        } else {
            $students = Student::where('name', 'like', "%$search%")
                ->where('is_verified', $isAccepted)
                ->paginate($limit);
        }

        return response()->json($students, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/student',
    summary: 'Create student',
    tags: ['Student'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: [
                    'username',
                    'email',
                    'password',
                    'name'
                ],
                properties: [
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(
                        property: 'school_id',
                        type: 'integer',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'image',
                        type: 'string',
                        format: 'binary',
                        nullable: true
                    )
                ]
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Student created successfully'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function store(StudentCreateRequest $request)
    {

        $data = $request->validated();

        $user = new User();
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->role = 'student';
        $user->password = $data['password'];

        if ($request->file('image')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('image')->getClientOriginalExtension();
            $user->photo_profile = $filename;
            $request->file('image')->storeAs('photo-profile', $filename, 'public');
        }
        $user->save();


        $student = new Student();
        $student->name = $data['name'];

        if ($request->user()->tokenCan('school-access')) {
            $student->school_id = $request->user()->school->id;
            $student->is_verified = true;
        } else {
            $student->school_id = $data['school_id'];
        }
        $student->user_id = $user->id;
        $student->class = $data['class'] ?? null;
        $student->major_id = $data['major_id'] ?? null;
        $student->gender = $data['gender'] ?? null;
        $student->address = $data['address'] ?? null;
        $student->phone_number = $data['phone_number'] ?? null;
        $student->date_of_birth = $data['date_of_birth'] ?? null;
        $student->save();

        return response()->json([
            'data' => $student->load(['user']),
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: '/student/{id}',
    summary: 'Get student detail',
    tags: ['Student'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Student retrieved successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        ),
        new OA\Response(
            response: 404,
            description: 'Student not found'
        )
    ]
)]
    public function show(string $id, Request $request)
    {
        $student = Student::with(['user'])->find($id);

        if (!$student) {
            throw new HttpResponseException(response([
                "errors" => "Student not found."
            ], 404));
        }

        if ($request->user()->tokenCan('school-access') && $student->school_id !== $request->user()->school->id) {
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
    #[OA\Put(
    path: '/student/{id}',
    summary: 'Update student',
    tags: ['Student'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'password', type: 'string'),
                    new OA\Property(property: 'school_id', type: 'integer'),
                    new OA\Property(
                        property: 'image',
                        type: 'string',
                        format: 'binary',
                        nullable: true
                    )
                ]
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Student updated successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Student not found'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function update(string $id, StudentUpdateRequest $request)
    {
        $student = Student::find($id);

        if (!$student) {
            throw new HttpResponseException(response([
                "errors" => "Student not found."
            ], 404));
        }

        $data = $request->validated();

        $student->name = $data['name'] ?? $student->name;
        $student->date_of_birth = $data['date_of_birth'] ?? $student->date_of_birth;
        $student->gender = $data['gender'] ?? $student->gender;
        $student->address = $data['address'] ?? $student->address;
        $student->phone_number = $data['phone_number'] ?? $student->phone_number;
        $student->class = $data['class'] ?? $student->class;
        $student->major_id = $data['major_id'] ?? $student->major_id;

        if ($request->user()->role === 'super_admin' && isset($data['school_id'])) {
            $student->school_id = $data['school_id'];
        }

        $student->save();

        if ($request->file('image')) {
            $filename = now()->format('Ymd_His') . '.' . $request->file('image')->getClientOriginalExtension();
            $user = $student->user;
            
            if ($user->photo_profile && Storage::disk('public')->exists("photo-profile/{$user->photo_profile}")) {
                Storage::disk('public')->delete("photo-profile/{$user->photo_profile}");
            }
            
            $user->photo_profile = $filename;
            $request->file('image')->storeAs('photo-profile', $filename, 'public');
            $user->save();
        }

        return response()->json([
            'message' => 'Student updated successfully',
            'data' => $student->load(['user']),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/student/{id}',
    summary: 'Delete student',
    tags: ['Student'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Student deleted successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Student not found'
        )
    ]
)]
    public function destroy(string $id)
    {
        $student = Student::find($id);

        if (!$student) {
            throw new HttpResponseException(response([
                "errors" => "Student not found"
            ], 404));
        }

        $user = $student->user;
        $student->delete();
        if ($user) {
            $user->delete();
        }

        return response()->json([
            'message' => 'OK',
        ], 200);
    }

    /**
     * Get student count by status for the authenticated user's school.
     */
    #[OA\Get(
    path: '/student/count',
    summary: 'Get student count by internship status',
    tags: ['Student'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Student count retrieved successfully'
        )
    ]
)]
    public function studentCount(Request $request)
    {

        $allStatuses = ['not_started', 'ongoing', 'completed'];

        $studentCount = collect($allStatuses)
            ->mapWithKeys(function ($status) use ($request) {
                $count = Student::where('school_id', $request->user()?->school?->id)
                    ->where('is_verified', true)
                    ->where('status', $status)
                    ->count();
                return [$status => $count];
            });


        return response()->json([
            'data' => $studentCount
        ], 200);
    }

}

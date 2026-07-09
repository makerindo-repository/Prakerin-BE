<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Internship;
use App\Models\Student;
use App\Models\Task;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Log;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
    path: '/task',
    summary: 'Get task list',
    tags: ['Task'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: 'pending, in_progress, completed, cancelled',
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'user_student_id',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer')
        ),
        new OA\Parameter(
            name: 'is_deadline',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'boolean')
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', default: 10)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Task list retrieved successfully'
        )
    ]
)]
    public function index(Request $request)
{
    $limit = request()->query('limit', 10);
    $search = request()->query('search', '');
    $status = request()->query('status', '');
    $userStudentId = $request->query('user_student_id', null);
    $isDeadline = filter_var($request->query('is_deadline', false), FILTER_VALIDATE_BOOLEAN);

    $user = $request->user();

    $tasks = Task::query();

    if (!is_null($user->student)) {
        // Student cuma boleh lihat task miliknya sendiri
        $tasks->whereHas('internship', function ($query) use ($user) {
            $query->where('student_id', $user->student->id);
        });
    } elseif (!is_null($user->company)) {
        // Company cuma boleh lihat task dari internship miliknya
        $tasks->whereHas('internship', function ($query) use ($user) {
            $query->where('company_id', $user->company->id);
        });

        // Filter tambahan by student tertentu (opsional, khusus company)
        if (!is_null($userStudentId)) {
            $student = Student::where('user_id', $userStudentId)->first();

            if ($student) {
                $tasks->whereHas('internship', function ($query) use ($student) {
                    $query->where('student_id', $student->id);
                });
            } else {
                $tasks->whereRaw('1 = 0'); // student tidak ditemukan -> kosong
            }
        }
    } else {
        // Role tidak dikenali -> FAIL SAFE, jangan tampilkan apa pun
        $tasks->whereRaw('1 = 0');
    }

    $tasks->when($isDeadline, function ($query) {
            $query->with('internship.student:id,name');
            $query->whereDate('due_date', '<=', now());
            $query->whereNot('status', 'completed');
        })
        ->with([
            'internship' => function ($q) {
                $q->select('id', 'student_id')->with(['student:id,name']);
            }
        ])
        ->where('title', 'like', '%' . $search . '%')
        ->when(in_array($status, ['pending', 'in_progress', 'completed', 'cancelled']), function ($query) use ($status) {
            $query->where('status', $status);
        });

    $tasks = $tasks->selectRaw("
            id, title, internship_id, status, due_date, created_at,
            CASE WHEN status = 'completed' THEN updated_at ELSE NULL END as updated_at
        ")
        ->orderBy('updated_at', 'desc')
        ->paginate($limit);

    return response()->json($tasks);
}

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/task',
    summary: 'Create task',
    tags: ['Task'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'internship_id',
                'title',
                'description',
                'due_date'
            ],
            properties: [
                new OA\Property(property: 'internship_id', type: 'integer'),
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(
                    property: 'due_date',
                    type: 'string',
                    format: 'date'
                ),
                new OA\Property(
                    property: 'link',
                    type: 'string',
                    nullable: true,
                    format: 'uri'
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Task created successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        )
    ]
)]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'internship_id' => 'required|exists:internships,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'due_date' => 'required|date',
            'link' => 'nullable|active_url'
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $data = $validator->validated();
        $user = $request->user();

        // Pastikan internship_id yang dikirim benar-benar milik company yang login
        if (!is_null($user->company)) {
            $ownsInternship = Internship::where('id', $data['internship_id'])
                ->where('company_id', $user->company->id)
                ->exists();

            if (!$ownsInternship) {
                throw new HttpResponseException(response()->json([
                    'errors' => 'Internship tidak ditemukan atau bukan milik company Anda.'
                ], 403));
            }
        }

        Task::create($data);

        return response()->json(['data' => true], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: '/task/{id}',
    summary: 'Get task detail',
    tags: ['Task'],
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
            description: 'Task retrieved successfully'
        ),
        new OA\Response(
            response: 404,
            description: 'Task not found'
        )
    ]
)]
    public function show(Request $request, string $id)
    {
        $task = Task::find($id);

        if (!$task) {
            throw new HttpResponseException(response()->json([
                'message' => 'Task not found.'
            ], 404));
        }


        // if ($studentId !== auth()->user()->student->id) {
        //     throw new HttpResponseException(response()->json([
        //         'message' => 'Forbidden.'
        //     ], 403));

        // }

        $user = $request->user();

        if (isset($user->company)) {
            $task['phone_number'] = isset($task->internship->student->phone_number) ? $this->normalizePhone($task->internship->student->phone_number) : null;
        } else {
            $task['phone_number'] = isset($task->internship->company->phone_number) ? $this->normalizePhone($task->internship->company->phone_number) : null;
        }

        $task->makeHidden(['internship']);


        return response()->json(['data' => $task], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
    path: '/task/{id}',
    summary: 'Update task status',
    tags: ['Task'],
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
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(
                    property: 'status',
                    type: 'string',
                    enum: [
                        'pending',
                        'in_progress',
                        'completed',
                        'cancelled'
                    ]
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Task updated successfully'
        ),
        new OA\Response(
            response: 400,
            description: 'Validation Error'
        ),
        new OA\Response(
            response: 404,
            description: 'Task not found'
        )
    ]
)]
    public function update(Request $request, string $id)
    {
        $task = Task::find($id);

        if (!$task) {
            throw new HttpResponseException(response()->json([
                'message' => 'Task not found.'
            ], 404));
        }

        $studentId = $task->internship->student_id;

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $task->update($validator->validated());
        $task->save();


        return response()->json(['data' => true], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
    path: '/task/{id}',
    summary: 'Delete task',
    tags: ['Task'],
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
            description: 'Task deleted successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        ),
        new OA\Response(
            response: 404,
            description: 'Task not found'
        )
    ]
)]
    public function destroy(string $id)
    {
        $task = Task::find($id);
        if (!$task) {
            throw new HttpResponseException(response()->json(['errors' => 'Task not found,'], 404));
        }

        if ($task->internship->internshipApplications->jobOpening->company_id !== auth()->user()->company->id) {
            throw new HttpResponseException(response()->json(['errors' => 'Forbidden.'], 403));
        }

        $task->delete();

        return response()->json(['data' => 'Task deleted successfully.'], 200);
    }


    #[OA\Get(
    path: '/task/count',
    summary: 'Get task statistics',
    tags: ['Task'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Task statistics retrieved successfully'
        )
    ]
)]
    public function count(Request $request)
    {
        $user = $request->user();

        $allStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        $data = collect($allStatuses)->mapWithKeys(function ($status) use ($user) {
            return [
                $status => Task::whereHas('internship', function ($q) use ($user) {
                    $q->where('company_id', $user->company->id);
                })->where('status', $status)->count()
            ];
        });

        $students = DB::table('internships')
            ->join('students', 'internships.student_id', '=', 'students.id')
            ->leftJoin('tasks', function ($join) {
                $join->on('internships.id', '=', 'tasks.internship_id')
                    ->where('tasks.status', 'completed');
            })
            ->where('internships.company_id', $user->company->id)
            ->where('internships.is_completed', false)
            ->select('students.name', DB::raw('COUNT(tasks.id) as completed_tasks'))
            ->groupBy('students.id', 'students.name')
            ->get();

        $data['students'] = $students;

        return response()->json(['data' => $data], 200);
    }

    private function normalizePhone(string $phone): string
    {
        // Hapus semua spasi dan tanda hubung
        $phone = preg_replace('/[\s\-]/', '', $phone);

        // Ganti prefix +62 → 62
        if (strpos($phone, '+62') === 0) {
            $phone = '62' . substr($phone, 3);
        }

        // Ganti prefix 0 → 62
        if (strpos($phone, '0') === 0) {
            $phone = '62' . substr($phone, 1);
        }

        return $phone;
    }



}

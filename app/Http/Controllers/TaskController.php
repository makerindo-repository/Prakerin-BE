<?php

namespace App\Http\Controllers;

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
    public function index(Request $request)
    {
        $limit = request()->query('limit', 10);
        $search = request()->query('search', '');
        $status = request()->query('status', '');
        $userStudentId = $request->query("user_student_id", null);

        $user = $request->user();

        $studentId = Student::where('user_id', $userStudentId)->first();

        $tasks = Task::
            when(isset($user->student), function ($query) use ($user) {
                $query->whereHas('internship', function ($query) use ($user) {
                    $query->where('student_id', $user->student->id);
                });
            })
            ->when(isset($user->company), function ($query) use ($user, $studentId) {
                $query->whereHas('internship', function ($query) use ($user, $studentId) {
                    $query->where('company_id', $user->company->id);
                    $query->when(isset($studentId), function ($query) use ($studentId) {
                        $query->where('student_id', $studentId);
                    });
                });
            })
            ->where('title', 'like', "%$search%")
            ->when($status === 'pending' || $status === 'in_progress' || $status === 'completed' || $status === 'cancelled', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->selectRaw("
                id,
                title,
                status,
                due_date,
                created_at,
                CASE 
                    WHEN status = 'completed' THEN updated_at 
                    ELSE NULL 
                END as updated_at
            ")
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);



        return response()->json($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
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

        Task::create($validator->validated());

        return response()->json(['data' => true], 201);
    }

    /**
     * Display the specified resource.
     */
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

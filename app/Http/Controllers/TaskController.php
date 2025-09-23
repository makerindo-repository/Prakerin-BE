<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

        $user = $request->user();

        $tasks = Task::
            when(isset($user->student), function ($query) use ($user) {
                $query->whereHas('internship', function ($query) use ($user) {
                    $query->where('student_id', $user->student->id);
                });
            })
            ->when(isset($user->company), function ($query) use ($user) {
                $query->whereHas('internship', function ($query) use ($user) {
                    $query->where('company_id', $user->company->id);
                });
            })
            ->where('title', 'like', "%$search%")
            ->when($status === 'pending' || $status === 'in_progress' || $status === 'completed' || $status === 'cancelled', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->select(['id', 'title', 'status', 'due_date'])
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
            'link' => 'required|active_url'
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $task = Task::create($validator->validated());

        return response()->json(['data' => $task], 201);
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

        $studentId = $task->internship
            ->internshipApplications
            ->curriculumVitae
            ->student_id;

        if ($studentId !== auth()->user()->student->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Forbidden.'
            ], 403));
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 422));
        }

        $task->update($validator->validated());
        $task->save();
        $task->makeHidden(['internship']);


        return response()->json(['data' => $task], 200);
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
}

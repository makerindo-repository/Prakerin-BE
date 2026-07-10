<?php

namespace App\Http\Controllers;

use App\Models\Mentor;
use App\Models\MentorAssignment;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MentorController extends Controller
{
    /**
     * List all mentors (Public).
     */
    public function index(Request $request)
    {
        $mentors = Mentor::with('user')
            ->withCount(['assignments as active_assignments_count' => function ($q) {
                $q->whereNull('ended_at');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $mentors
        ]);
    }

    /**
     * Get candidates for mentor profile (Users who are not students and don't have mentor profile yet).
     */
    public function candidates()
    {
        $existingUserIds = Mentor::pluck('user_id')->toArray();

        $candidates = User::where('role', '!=', 'student')
            ->whereNotIn('id', $existingUserIds)
            ->orderBy('username', 'asc')
            ->get();

        return response()->json([
            'data' => $candidates
        ]);
    }

    /**
     * Get single mentor profile.
     */
    public function show(string $id)
    {
        $mentor = Mentor::with('user')->find($id);

        if (!$mentor) {
            throw new HttpResponseException(response()->json(['errors' => 'Mentor not found'], 404));
        }

        return response()->json([
            'data' => $mentor
        ]);
    }

    /**
     * Create mentor profile (Admin only).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|uuid|unique:mentors,user_id|exists:users,id',
            'expertise' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'availability' => 'required|in:available,limited,unavailable',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
        }

        $mentor = Mentor::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $mentor
        ], 201);
    }

    /**
     * Update mentor profile (Admin only).
     */
    public function update(Request $request, string $id)
    {
        $mentor = Mentor::find($id);

        if (!$mentor) {
            throw new HttpResponseException(response()->json(['errors' => 'Mentor not found'], 404));
        }

        $validator = Validator::make($request->all(), [
            'expertise' => 'string|max:255',
            'bio' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'availability' => 'in:available,limited,unavailable',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
        }

        $mentor->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $mentor
        ]);
    }

    /**
     * Delete mentor profile (Admin only).
     */
    public function destroy(string $id)
    {
        $mentor = Mentor::find($id);

        if (!$mentor) {
            throw new HttpResponseException(response()->json(['errors' => 'Mentor not found'], 404));
        }

        $mentor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mentor profile deleted successfully'
        ]);
    }

    /**
     * Get current assigned mentor (Student only).
     */
    public function myMentor()
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['errors' => 'Only students can check assigned mentors'], 403);
        }

        $assignment = MentorAssignment::with(['mentor.user', 'assignedBy'])
            ->where('student_id', $user->id)
            ->whereNull('ended_at')
            ->first();

        return response()->json([
            'data' => $assignment
        ]);
    }

    /**
     * Assign mentor to student (Admin or School).
     */
    public function assign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|uuid|exists:users,id',
            'mentor_id' => 'required|uuid|exists:mentors,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
        }

        // Check if student exists and has 'student' role
        $student = User::find($request->student_id);
        if ($student->role !== 'student') {
            return response()->json(['errors' => 'The assigned user must be a student'], 422);
        }

        // End previous active assignment if exists
        MentorAssignment::where('student_id', $request->student_id)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => Carbon::now(),
            ]);

        $assignment = MentorAssignment::create([
            'student_id' => $request->student_id,
            'mentor_id' => $request->mentor_id,
            'assigned_by_id' => Auth::id(),
            'assigned_at' => Carbon::now(),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $assignment
        ], 201);
    }

    /**
     * List all assignments (Admin only).
     */
    public function assignments(Request $request)
    {
        $status = $request->query('status'); // active / ended

        $query = MentorAssignment::with(['student', 'mentor.user', 'assignedBy'])
            ->orderBy('assigned_at', 'desc');

        if ($status === 'active') {
            $query->whereNull('ended_at');
        } elseif ($status === 'ended') {
            $query->whereNotNull('ended_at');
        }

        $assignments = $query->get();

        return response()->json([
            'data' => $assignments
        ]);
    }

    /**
     * End active assignment.
     */
    public function endAssignment(string $id)
    {
        $assignment = MentorAssignment::find($id);

        if (!$assignment) {
            throw new HttpResponseException(response()->json(['errors' => 'Assignment not found'], 404));
        }

        if ($assignment->ended_at) {
            return response()->json(['errors' => 'Assignment has already ended'], 422);
        }

        $assignment->update([
            'ended_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mentor assignment ended successfully'
        ]);
    }
}

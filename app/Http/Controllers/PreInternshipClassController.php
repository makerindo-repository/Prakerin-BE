<?php

namespace App\Http\Controllers;

use App\Models\PreInternshipClass;
use App\Models\PreInternshipEnrollment;
use App\Models\ClassAttendance;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PreInternshipClassController extends Controller
{
    /**
     * List available classes (Public).
     */
    public function index(Request $request)
    {
        $level = $request->query('level');

        $query = PreInternshipClass::withCount(['enrollments as enrolled_count' => function ($q) {
            $q->where('status', 'enrolled');
        }])->where('status', '!=', 'completed');

        if ($level) {
            $query->where('level', $level);
        }

        $classes = $query->orderBy('start_date', 'asc')->get();

        return response()->json([
            'data' => $classes
        ]);
    }

    /**
     * Create a pre-internship class (School/Company/Admin).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date_format:Y-m-d H:i:s',
            'end_date' => 'required|date_format:Y-m-d H:i:s|after:start_date',
            'capacity' => 'required|integer|min:1',
            'level' => 'required|in:beginner,intermediate,advanced',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
        }

        $user = Auth::user();
        
        $class = PreInternshipClass::create([
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'capacity' => $request->capacity,
            'level' => $request->level,
            'status' => 'scheduled',
            'created_by_id' => $user->id,
            'created_by_type' => in_array($user->role, ['school', 'company']) ? $user->role : 'school',
        ]);

        return response()->json([
            'success' => true,
            'data' => $class
        ], 201);
    }

    /**
     * Update a class (Admin or Creator).
     */
    public function update(Request $request, string $id)
    {
        $class = PreInternshipClass::find($id);

        if (!$class) {
            throw new HttpResponseException(response()->json(['errors' => 'Class not found'], 404));
        }

        // Authorization check
        $user = Auth::user();
        if ($user->role !== 'super_admin' && $class->created_by_id !== $user->id) {
            return response()->json(['errors' => 'Unauthorized'], 403);
        }

        // Cannot update if class already started
        if (Carbon::parse($class->start_date)->isPast()) {
            return response()->json(['errors' => 'Cannot update a class that has already started'], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'date_format:Y-m-d H:i:s',
            'end_date' => 'date_format:Y-m-d H:i:s|after:start_date',
            'capacity' => 'integer|min:1',
            'level' => 'in:beginner,intermediate,advanced',
            'status' => 'in:scheduled,ongoing,completed',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
        }

        $class->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $class
        ]);
    }

    /**
     * Delete class (Admin or Creator).
     */
    public function destroy(string $id)
    {
        $class = PreInternshipClass::find($id);

        if (!$class) {
            throw new HttpResponseException(response()->json(['errors' => 'Class not found'], 404));
        }

        $user = Auth::user();
        if ($user->role !== 'super_admin' && $class->created_by_id !== $user->id) {
            return response()->json(['errors' => 'Unauthorized'], 403);
        }

        // Cannot delete if there are active enrollments
        $hasEnrollments = PreInternshipEnrollment::where('class_id', $class->id)
            ->where('status', 'enrolled')
            ->exists();

        if ($hasEnrollments) {
            return response()->json(['errors' => 'Cannot delete a class with active enrollments'], 422);
        }

        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class deleted successfully'
        ]);
    }

    /**
     * Enroll in a class (Student only).
     */
    public function enroll(string $id)
    {
        $class = PreInternshipClass::find($id);

        if (!$class) {
            throw new HttpResponseException(response()->json(['errors' => 'Class not found'], 404));
        }

        $user = Auth::user();
        if ($user->role !== 'student') {
            return response()->json(['errors' => 'Only students can enroll in classes'], 403);
        }

        // Check capacity
        $enrolledCount = PreInternshipEnrollment::where('class_id', $class->id)
            ->where('status', 'enrolled')
            ->count();

        if ($enrolledCount >= $class->capacity) {
            return response()->json(['errors' => 'Class capacity is full'], 422);
        }

        // Check if already enrolled
        $existing = PreInternshipEnrollment::where('class_id', $class->id)
            ->where('student_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'enrolled') {
                return response()->json(['errors' => 'Already enrolled in this class'], 422);
            }
            // Re-enroll if previously dropped
            $existing->update([
                'status' => 'enrolled',
                'enrolled_at' => Carbon::now(),
            ]);
            return response()->json(['success' => true, 'message' => 'Enrolled successfully']);
        }

        PreInternshipEnrollment::create([
            'student_id' => $user->id,
            'class_id' => $class->id,
            'status' => 'enrolled',
            'attendance_count' => 0,
            'total_sessions' => 0,
            'enrolled_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enrolled successfully'
        ], 201);
    }

    /**
     * Drop a class (Student only).
     */
    public function drop(string $id)
    {
        $enrollment = PreInternshipEnrollment::find($id);

        if (!$enrollment) {
            throw new HttpResponseException(response()->json(['errors' => 'Enrollment not found'], 404));
        }

        $user = Auth::user();
        if ($enrollment->student_id !== $user->id) {
            return response()->json(['errors' => 'Unauthorized'], 403);
        }

        if ($enrollment->status !== 'enrolled') {
            return response()->json(['errors' => 'Can only drop currently enrolled classes'], 422);
        }

        $enrollment->update([
            'status' => 'dropped'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dropped class successfully'
        ]);
    }

    /**
     * Get logged-in student's classes.
     */
    public function myClasses()
    {
        $user = Auth::user();
        
        $enrollments = PreInternshipEnrollment::with('class')
            ->where('student_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $enrollments
        ]);
    }

    /**
     * List enrollments for a class (Admin or Creator).
     */
    public function classEnrollments(string $id)
    {
        $class = PreInternshipClass::find($id);

        if (!$class) {
            throw new HttpResponseException(response()->json(['errors' => 'Class not found'], 404));
        }

        $user = Auth::user();
        if ($user->role !== 'super_admin' && $class->created_by_id !== $user->id) {
            return response()->json(['errors' => 'Unauthorized'], 403);
        }

        $enrollments = PreInternshipEnrollment::with('student')
            ->where('class_id', $class->id)
            ->get();

        return response()->json([
            'data' => $enrollments
        ]);
    }

    /**
     * Mark class attendance (Admin or Creator).
     */
    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enrollment_id' => 'required|exists:pre_internship_enrollments,id',
            'session_date' => 'required|date_format:Y-m-d H:i:s',
            'present' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
        }

        $enrollment = PreInternshipEnrollment::with('class')->find($request->enrollment_id);
        
        // Authorization check
        $user = Auth::user();
        if ($user->role !== 'super_admin' && $enrollment->class->created_by_id !== $user->id) {
            return response()->json(['errors' => 'Unauthorized'], 403);
        }

        $attendance = ClassAttendance::create([
            'enrollment_id' => $request->enrollment_id,
            'session_date' => $request->session_date,
            'present' => $request->present,
            'notes' => $request->notes,
        ]);

        // Recalculate attendance stats
        $totalSessions = ClassAttendance::where('enrollment_id', $enrollment->id)->count();
        $presentCount = ClassAttendance::where('enrollment_id', $enrollment->id)->where('present', true)->count();

        $enrollment->update([
            'attendance_count' => $presentCount,
            'total_sessions' => $totalSessions,
        ]);

        return response()->json([
            'success' => true,
            'data' => $attendance
        ], 201);
    }

    /**
     * Get attendance record (Student or Admin/Creator).
     */
    public function attendanceRecord(string $id)
    {
        $enrollment = PreInternshipEnrollment::with('class')->find($id);

        if (!$enrollment) {
            throw new HttpResponseException(response()->json(['errors' => 'Enrollment not found'], 404));
        }

        $user = Auth::user();
        if ($user->role !== 'super_admin' && $enrollment->student_id !== $user->id && $enrollment->class->created_by_id !== $user->id) {
            return response()->json(['errors' => 'Unauthorized'], 403);
        }

        $records = ClassAttendance::where('enrollment_id', $enrollment->id)
            ->orderBy('session_date', 'asc')
            ->get();

        return response()->json([
            'data' => $records,
            'enrollment' => $enrollment
        ]);
    }
}

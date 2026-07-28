<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\StudentAward;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DB;

class AwardController extends Controller
{
    // POST /api/v1/awards
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'required|string', // Lucide icon name
            'category' => 'required|in:achievement,excellence,participation,special',
            'point_value' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $award = Award::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'icon' => $request->input('icon'),
            'category' => $request->input('category'),
            'point_value' => $request->input('point_value'),
            'is_active' => $request->input('is_active', true),
            'created_by_id' => Auth::id()
        ]);

        return response()->json(['data' => $award], 201);
    }

    // GET /api/v1/awards
    public function index(Request $request)
    {
        $category = $request->query('category');
        $isActive = $request->query('is_active');
        $limit = $request->query('limit', 15);

        $query = Award::query();

        if ($category) {
            $query->where('category', $category);
        }
        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $awards = $query->paginate($limit);
        return response()->json($awards);
    }

    // GET /api/v1/awards/{id}
    public function show($id)
    {
        $award = Award::withCount('studentAwards')
            ->with('studentAwards.student.student')
            ->findOrFail($id);
        return response()->json(['data' => $award]);
    }

    // PATCH /api/v1/awards/{id}
    public function update(Request $request, $id)
    {
        $award = Award::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'category' => 'nullable|in:achievement,excellence,participation,special',
            'point_value' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $award->update($request->only(['name', 'description', 'icon', 'category', 'point_value', 'is_active']));

        return response()->json(['data' => $award]);
    }

    // DELETE /api/v1/awards/{id}
    public function destroy($id)
    {
        $award = Award::findOrFail($id);

        // Prevent deletion if already assigned to students
        if ($award->studentAwards()->count() > 0) {
            return response()->json([
                'errors' => 'Cannot delete award because it is already assigned to students.'
            ], 400);
        }

        $award->delete();
        return response()->json(['message' => 'Award deleted successfully']);
    }

    // POST /api/v1/student-awards
    public function assign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|uuid|exists:users,id',
            'award_id' => 'required|uuid|exists:awards,id',
            'reason' => 'nullable|string',
            'is_public' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $studentId = $request->input('student_id');
        $awardId = $request->input('award_id');

        // Check if student user exists and is student role
        $studentUser = User::findOrFail($studentId);
        if ($studentUser->role !== 'student') {
            return response()->json(['errors' => ['student_id' => ['User is not a student.']]], 422);
        }

        // Allow student to be assigned the same award multiple times

        $studentAward = StudentAward::create([
            'student_id' => $studentId,
            'award_id' => $awardId,
            'reason' => $request->input('reason'),
            'awarded_at' => Carbon::now(),
            'awarded_by_id' => Auth::id(),
            'is_public' => $request->input('is_public', true)
        ]);

        return response()->json(['data' => $studentAward->load('award', 'student', 'awardedBy')], 201);
    }

    // DELETE /api/v1/student-awards/{id}
    public function removeAssignment($id)
    {
        $studentAward = StudentAward::findOrFail($id);
        $studentAward->delete();
        return response()->json(['message' => 'Award removed from student successfully']);
    }

    // GET /api/v1/students/{studentId}/awards
    public function studentAwards($studentId)
    {
        $user = User::findOrFail($studentId);

        // Fetch awards
        $awards = StudentAward::where('student_id', $studentId)
            ->where('is_public', true)
            ->with('award', 'awardedBy')
            ->get();

        $totalPoints = $awards->sum(function ($item) {
            return $item->award->point_value ?? 0;
        });

        return response()->json([
            'student_name' => $user->student->name ?? $user->username,
            'total_points' => $totalPoints,
            'awards' => $awards
        ]);
    }

    // GET /api/v1/awards/leaderboard
    public function leaderboard(Request $request)
    {
        $category = $request->query('category');

        $query = StudentAward::join('awards', 'student_awards.award_id', '=', 'awards.id')
            ->join('users', 'student_awards.student_id', '=', 'users.id')
            ->leftJoin('students', 'users.id', '=', 'students.user_id')
            ->select(
                'users.id as student_user_id',
                'students.name as student_name',
                'users.username',
                'users.photo_profile',
                DB::raw('SUM(awards.point_value) as total_points'),
                DB::raw('COUNT(student_awards.id) as awards_count')
            );

        if ($category) {
            $query->where('awards.category', $category);
        }

        $leaderboard = $query->groupBy('users.id', 'students.name', 'users.username', 'users.photo_profile')
            ->orderBy('total_points', 'desc')
            ->orderBy('awards_count', 'desc')
            ->limit(20)
            ->get();

        // Include top 3 awards for each
        foreach ($leaderboard as $student) {
            $student->top_awards = StudentAward::where('student_id', $student->student_user_id)
                ->with('award')
                ->limit(3)
                ->get()
                ->pluck('award');
        }

        return response()->json($leaderboard);
    }

    // GET /api/v1/student-awards/{id}/certificate
    public function printCertificate($id)
    {
        $studentAward = StudentAward::with('award', 'student.student', 'awardedBy')->findOrFail($id);

        $studentName = $studentAward->student->student->name ?? $studentAward->student->username;
        $awardName = $studentAward->award->name;
        $date = Carbon::parse($studentAward->awarded_at)->format('d F Y');
        $reason = $studentAward->reason ?? 'Outstanding performance and dedication';
        $awardCategory = ucfirst($studentAward->award->category);

        $html = "
        <html>
        <head>
            <style>
                body {
                    font-family: 'Georgia', serif;
                    background-color: #f7f9fa;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .certificate-container {
                    border: 15px double #035a70;
                    padding: 40px;
                    background-color: #ffffff;
                    text-align: center;
                    width: 90%;
                    max-width: 800px;
                    margin: 20px auto;
                    box-shadow: 0 0 20px rgba(0,0,0,0.1);
                    position: relative;
                }
                .logo {
                    font-size: 28px;
                    font-weight: bold;
                    color: #035a70;
                    margin-bottom: 20px;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
                .title {
                    font-size: 38px;
                    font-weight: bold;
                    color: #b5952c;
                    margin-top: 10px;
                    margin-bottom: 5px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .subtitle {
                    font-size: 14px;
                    font-style: italic;
                    color: #666;
                    margin-bottom: 30px;
                }
                .awarded-to {
                    font-size: 18px;
                    color: #555;
                    margin-bottom: 10px;
                }
                .student-name {
                    font-size: 30px;
                    font-weight: bold;
                    color: #035a70;
                    border-bottom: 2px solid #ddd;
                    display: inline-block;
                    padding-bottom: 5px;
                    margin-bottom: 25px;
                }
                .award-text {
                    font-size: 16px;
                    line-height: 1.6;
                    color: #444;
                    max-width: 600px;
                    margin: 0 auto 35px auto;
                }
                .footer-info {
                    margin-top: 55px;
                }
                .signature-section {
                    float: left;
                    width: 45%;
                    text-align: center;
                }
                .date-section {
                    float: right;
                    width: 45%;
                    text-align: center;
                }
                .line {
                    border-top: 1px solid #777;
                    width: 180px;
                    margin: 40px auto 5px auto;
                }
                .label {
                    font-size: 11px;
                    text-transform: uppercase;
                    color: #777;
                    letter-spacing: 1px;
                }
                .badge-cat {
                    font-size: 11px;
                    background-color: #035a70;
                    color: #fff;
                    padding: 5px 12px;
                    border-radius: 15px;
                    display: inline-block;
                    margin-bottom: 15px;
                    text-transform: uppercase;
                    font-weight: bold;
                }
                .clearfix::after {
                    content: '';
                    clear: both;
                    display: table;
                }
            </style>
        </head>
        <body>
            <div class='certificate-container'>
                <div class='logo'>Prakerin ID</div>
                <div class='badge-cat'>Category: $awardCategory</div>
                <div class='title'>Certificate of Award</div>
                <div class='subtitle'>This document verifies the official achievement of pre-internship performance</div>
                
                <div class='awarded-to'>PROUDLY PRESENTED TO</div>
                <div class='student-name'>$studentName</div>
                
                <div class='award-text'>
                    For successfully obtaining the <strong>$awardName</strong> Award. <br>
                    Given in recognition of: <br>
                    <span style='font-style: italic; color: #555;'>\"$reason\"</span>
                </div>
                
                <div class='footer-info clearfix'>
                    <div class='signature-section'>
                        <div class='line'></div>
                        <div class='label'>Authorized Administrator</div>
                    </div>
                    <div class='date-section'>
                        <div style='font-weight: bold; margin-top: 15px; font-size: 14px;'>$date</div>
                        <div class='line' style='margin-top: 10px;'></div>
                        <div class='label'>Date Issued</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $pdf = Pdf::loadHTML($html);
        return $pdf->download("certificate_{$studentName}_" . str_replace(' ', '_', strtolower($awardName)) . ".pdf");
    }
}
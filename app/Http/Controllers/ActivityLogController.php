<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->query('limit', 50);
        $userId = $request->query('user_id');
        $action = $request->query('action');
        $resourceType = $request->query('resource_type');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $search = $request->query('search');

        $query = ActivityLog::with('user');

        if ($userId) {
            $query->byUser($userId);
        }
        if ($action) {
            $query->byAction($action);
        }
        if ($resourceType) {
            $query->byResourceType($resourceType);
        }
        if ($startDate || $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('resource_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->recent()->paginate($limit);

        return response()->json($logs);
    }

    public function stats()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        $totalToday = ActivityLog::where('created_at', '>=', $today)->count();
        $totalWeek = ActivityLog::where('created_at', '>=', $thisWeek)->count();
        $totalMonth = ActivityLog::where('created_at', '>=', $thisMonth)->count();

        // Most active user
        $mostActiveUser = ActivityLog::select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->with('user')
            ->first();

        // Most logged action
        $mostLoggedAction = ActivityLog::select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->first();

        // Most modified resource
        $mostModifiedResource = ActivityLog::select('resource_type', DB::raw('count(*) as count'))
            ->groupBy('resource_type')
            ->orderBy('count', 'desc')
            ->first();

        // Login count today
        $loginsToday = ActivityLog::where('action', 'login')
            ->where('created_at', '>=', $today)
            ->count();

        return response()->json([
            'total_today' => $totalToday,
            'total_week' => $totalWeek,
            'total_month' => $totalMonth,
            'most_active_user' => $mostActiveUser ? [
                'username' => $mostActiveUser->user->username ?? 'Deleted User',
                'count' => $mostActiveUser->count
            ] : null,
            'most_logged_action' => $mostLoggedAction ? [
                'action' => $mostLoggedAction->action,
                'count' => $mostLoggedAction->count
            ] : null,
            'most_modified_resource' => $mostModifiedResource ? [
                'resource_type' => $mostModifiedResource->resource_type,
                'count' => $mostModifiedResource->count
            ] : null,
            'logins_today' => $loginsToday
        ]);
    }
}

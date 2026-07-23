<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Revenue;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    // ── GET /api/v1/admin/revenue/dashboard ───────────────────────────────

    /**
     * Summary KPIs for the revenue dashboard.
     */
    public function dashboard(): JsonResponse
    {
        $totalRevenue = Revenue::where('payment_status', 'paid')->sum('amount');

        $activePremium = Student::where('status_subscription', 'premium')->count();

        $pendingPayments = Revenue::where('payment_status', 'pending')->count();

        $thisMonthRevenue = Revenue::where('payment_status', 'paid')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $revenueByTier = [
            'monthly' => Revenue::where('payment_status', 'paid')
                ->whereRaw('DATEDIFF(period_end, period_start) <= 31')
                ->sum('amount'),
            'yearly' => Revenue::where('payment_status', 'paid')
                ->whereRaw('DATEDIFF(period_end, period_start) > 31')
                ->sum('amount'),
        ];

        return response()->json([
            'total_revenue'           => (float) $totalRevenue,
            'active_premium_accounts' => $activePremium,
            'pending_payments'        => $pendingPayments,
            'this_month_revenue'      => (float) $thisMonthRevenue,
            'revenue_by_tier'         => $revenueByTier,
        ]);
    }

    // ── GET /api/v1/admin/revenue/accounts ────────────────────────────────

    /**
     * Filterable, paginated list of all revenue records.
     */
    public function accounts(Request $request): JsonResponse
    {
        $limit     = $request->integer('limit', 15);
        $status    = $request->input('status');    // all | paid | pending | failed
        $userType  = $request->input('user_type'); // all | siswa | mahasiswa
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $query = Revenue::with(['student', 'subscription'])
            ->when($status && $status !== 'all', fn ($q) => $q->where('payment_status', $status))
            ->when($userType && $userType !== 'all', fn ($q) => $q->where('user_type', $userType))
            ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate))
            ->latest();

        $records = $query->paginate($limit);

        $data = $records->map(function (Revenue $r) {
            $student = Student::find($r->user_id);
            return [
                'id'              => $r->id,
                'user_id'         => $r->user_id,
                'user_name'       => $student?->name ?? '—',
                'user_type'       => $r->user_type,
                'amount'          => (float) $r->amount,
                'currency'        => $r->currency,
                'payment_status'  => $r->payment_status,
                'payment_date'    => $r->payment_date,
                'period_start'    => $r->period_start,
                'period_end'      => $r->period_end,
                'xendit_invoice_id' => $r->xendit_invoice_id,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'total'        => $records->total(),
                'per_page'     => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
            ],
        ]);
    }
}

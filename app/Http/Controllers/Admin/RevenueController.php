<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Revenue;
use App\Models\Student;
use App\Services\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RevenueController extends Controller
{
    public function __construct(protected XenditService $xendit) {}

    /**
     * Sapu record pending yang sudah lewat batas waktu bayar, dijalankan
     * on-demand tiap kali endpoint Revenue Dashboard diakses.
     *
     * Ini alternatif dari `subscription:expire-pending-payments` yang
     * dijadwalkan lewat cron — kalau cron/scheduler Laravel di server belum
     * di-setup (butuh akses server, sering diurus tim infra terpisah),
     * approach ini tetap membuat status ke-update otomatis, cukup dengan
     * admin membuka/refresh halaman Revenue Dashboard. Di-throttle maksimal
     * sekali per menit (lewat cache) supaya tidak membombardir API Xendit
     * kalau halaman ini di-refresh berkali-kali dalam waktu singkat.
     */
    private function sweepIfDue(): void
    {
        try {
            Cache::remember('revenue_expiry_sweep_lock', 60, function () {
                $result = $this->xendit->sweepExpiredPending();
                Log::info('[RevenueController] On-demand sweep triggered by dashboard load', $result);
                return true;
            });
        } catch (\Throwable $e) {
            // Jangan sampai dashboard gagal load gara-gara sweep-nya error.
            Log::warning('[RevenueController] sweepIfDue failed: ' . $e->getMessage());
        }
    }

    // ── GET /api/v1/admin/revenue/dashboard ───────────────────────────────

    /**
     * Summary KPIs for the revenue dashboard.
     */
    public function dashboard(): JsonResponse
    {
        $this->sweepIfDue();

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
        $this->sweepIfDue();

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

    // ── POST /api/v1/admin/revenue/{id}/sync ──────────────────────────────

    /**
     * Cek ulang status invoice ini LANGSUNG ke Xendit, dan perbaiki record
     * yang statusnya nyangkut "pending" di DB kita padahal sudah lunas di
     * Xendit (mis. webhook belum di-setup / gagal nyampe, atau admin cuma
     * nutup modal QRIS sebelum polling sempat mendeteksi status "paid").
     *
     * Idempotent & aman dipanggil berkali-kali — kalau sudah paid ya
     * dibiarkan, kalau belum ya cuma dilaporkan statusnya masih pending.
     */
    public function syncStatus(string $id): JsonResponse
    {
        $revenue = Revenue::findOrFail($id);

        if ($revenue->payment_status === 'paid') {
            return response()->json([
                'message' => 'Record ini sudah berstatus paid.',
                'data'    => $revenue,
            ]);
        }

        if (!$revenue->xendit_invoice_id) {
            return response()->json([
                'errors' => 'Record ini belum punya xendit_invoice_id (invoice mungkin gagal dibuat), tidak bisa disinkronkan.',
            ], 422);
        }

        try {
            $status = $this->xendit->getInvoiceStatus($revenue->xendit_invoice_id);
        } catch (\Throwable $e) {
            return response()->json([
                'errors' => 'Gagal menghubungi Xendit.',
                'debug'  => $e->getMessage(),
            ], 502);
        }

        if ($status['paid']) {
            $this->xendit->confirmPayment($revenue->xendit_invoice_id, $revenue->external_id);

            return response()->json([
                'message' => 'Status berhasil disinkronkan — pembayaran dikonfirmasi lunas.',
                'data'    => $revenue->fresh(),
            ]);
        }

        return response()->json([
            'message' => "Status di Xendit masih \"{$status['status']}\" (belum dibayar), tidak ada yang diubah.",
            'data'    => $revenue,
        ]);
    }
}
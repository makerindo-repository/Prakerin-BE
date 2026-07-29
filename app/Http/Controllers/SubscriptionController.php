<?php

namespace App\Http\Controllers;

use App\Models\Revenue;
use App\Models\Student;
use App\Models\Subscription;
use App\Services\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(protected XenditService $xendit) {}

    // ── GET /api/v1/subscriptions/user/{userId} ────────────────────────────

    /**
     * Fetch the current subscription status for a student.
     */
    public function getUserSubscription(Request $request, string $userId): JsonResponse
    {
        $authUser = $request->user();
        $isOwner  = $authUser?->student?->id === $userId;
        $isAdmin  = $authUser?->role === 'super_admin';

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki akses ke data langganan siswa ini.',
            ], 403);
        }

        $student = Student::where('id', $userId)
            ->with(['subscription', 'school'])
            ->firstOrFail();

        $subscription = $student->subscription;

        return response()->json([
            'student_id'              => $student->id,
            'status_subscription'     => $student->status_subscription ?? 'free',
            'subscription_renewed_at' => $student->subscription_renewed_at,
            'subscription'            => $subscription ? [
                'id'                    => $subscription->id,
                'status'                => $subscription->status,
                'amount'                => $subscription->amount,
                'currency'              => $subscription->currency,
                'subscription_start_date' => $subscription->subscription_start_date,
                'subscription_end_date'   => $subscription->subscription_end_date,
                'renewal_date'          => $subscription->renewal_date,
                'is_expired'            => $subscription->isExpired(),
                'is_renewal_due'        => $subscription->isRenewalDue(),
            ] : null,
        ]);
    }

    // ── POST /api/v1/subscriptions/create-payment ──────────────────────────

    /**
     * Create a Xendit QRIS payment for a premium subscription upgrade.
     */
    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'package'    => 'required|in:monthly,yearly',
        ]);

        $authUser = $request->user();
        $isOwner  = $authUser?->student?->id === $validated['student_id'];
        $isAdmin  = $authUser?->role === 'super_admin';

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'errors' => 'Anda tidak bisa membuat pembayaran untuk akun siswa lain.',
            ], 403);
        }

        $student  = Student::with('school')->findOrFail($validated['student_id']);
        $packages = config('subscription.packages');
        $package  = $packages[$validated['package']];

        // Upsert subscription record
        $now          = now();
        $endDate      = $now->copy()->addDays($package['duration_days']);
        $renewalDate  = $now->copy()->addDays($package['duration_days']); // first renewal = end of current period

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $student->id],
            [
                'user_type'               => $student->subscription_user_type,
                'amount'                  => $package['amount'],
                'currency'                => 'IDR',
                'status'                  => 'pending_payment',
                'subscription_start_date' => $now,
                'subscription_end_date'   => $endDate,
                'renewal_date'            => $renewalDate,
                'payment_method'          => 'QRCODE',
            ]
        );

        // Create pending Revenue record
        // external_id di-generate & disimpan DI SINI (sebelum manggil Xendit),
        // supaya kalau response createInvoice gagal sampai ke server nanti
        // (timeout/crash setelah Xendit sebenarnya sudah berhasil bikin invoice),
        // webhook/polling yang masuk belakangan TETAP bisa mencocokkan record
        // ini lewat external_id (lihat XenditService::confirmPayment).
        $referenceId = 'SUB-' . strtoupper(Str::random(12)) . '-' . $student->id;
        $revenue = Revenue::create([
            'subscription_id'  => $subscription->id,
            'user_id'          => $student->id,
            'user_type'        => $student->subscription_user_type,
            'amount'           => $package['amount'],
            'currency'         => 'IDR',
            'payment_status'   => 'pending',
            'period_start'     => $now,
            'period_end'       => $endDate,
            'external_id'      => $referenceId,
        ]);
        $subscription->update(['external_id' => $referenceId]);

        // Create Xendit invoice
        try {
            $invoice = $this->xendit->createInvoice(
                $package['amount'],
                $student,
                $referenceId,
                $package['name']
            );
        } catch (\Throwable $e) {
            $revenue->delete();
            Log::error('[SubscriptionController] createInvoice failed: ' . $e->getMessage());

            // Sertakan pesan error ASLI (dari Xendit/exception PHP) di response —
            // aman ($e->getMessage() isinya body respons Xendit, bukan secret
            // key), supaya bisa didiagnosis dari tab Response DevTools browser
            // tanpa perlu akses storage/logs di server.
            return response()->json([
                'errors' => 'Gagal membuat invoice pembayaran. Coba lagi.',
                'debug'  => $e->getMessage(),
            ], 500);
        }

        // Store invoice ID on revenue + subscription
        $revenue->update(['xendit_invoice_id' => $invoice['id']]);
        $subscription->update(['xendit_invoice_id' => $invoice['id']]);

        return response()->json([
            'message'     => 'Invoice berhasil dibuat.',
            'invoice_id'  => $invoice['id'],
            'invoice_url' => $invoice['invoice_url'],
            'qr_code_url' => $invoice['qr_code_url'],
            'amount'      => $package['amount'],
            'package'     => $validated['package'],
            'expiry_date' => $invoice['expiry_date'] ?? null,
        ], 201);
    }

    // ── GET /api/v1/subscriptions/payment-status/{invoiceId} ──────────────

    /**
     * Poll Xendit for live payment status.
     * Used by the frontend every 5 seconds.
     */
    public function getPaymentStatus(Request $request, string $invoiceId): JsonResponse
    {
        $authUser = $request->user();
        $isAdmin  = $authUser?->role === 'super_admin';

        if (!$isAdmin) {
            $revenue = Revenue::where('xendit_invoice_id', $invoiceId)->first();
            $isOwner = $revenue && $authUser?->student?->id === $revenue->user_id;

            if (!$isOwner) {
                return response()->json([
                    'errors' => 'Anda tidak memiliki akses ke invoice ini.',
                ], 403);
            }
        }

        try {
            $status = $this->xendit->getInvoiceStatus($invoiceId);
        } catch (\Throwable $e) {
            return response()->json(['errors' => 'Gagal memeriksa status pembayaran.'], 502);
        }

        // Fallback kalau webhook Xendit belum/gagal nyampe: begitu polling
        // ini lihat status-nya sudah final (PAID/SETTLED atau
        // EXPIRED/FAILED), langsung jalankan proses yang sama seperti yang
        // dilakukan webhook. Idempotent, jadi aman kalau ternyata webhook-nya
        // nyampe duluan atau menyusul.
        if ($status['paid']) {
            $this->xendit->confirmPayment($invoiceId);
        } elseif (in_array($status['status'], ['EXPIRED', 'FAILED'])) {
            $this->xendit->markExpired($invoiceId);
        }

        return response()->json([
            'invoice_id' => $invoiceId,
            'paid'       => $status['paid'],
            'status'     => $status['status'],
            'message'    => $status['paid']
                ? 'Pembayaran berhasil dikonfirmasi.'
                : 'Menunggu pembayaran...',
        ]);
    }
}
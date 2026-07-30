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

        // Invoice pending & belum expired (kalau ada) — dipakai frontend
        // supaya tombol "Upgrade" langsung nampilin QR pembayaran yang sudah
        // ada, bukan nyuruh pilih paket dari awal lagi.
        $pendingRevenue = Revenue::where('user_id', $student->id)
            ->where('payment_status', 'pending')
            ->where('expiry_date', '>', now())
            ->latest()
            ->first();

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
            'pending_payment' => $pendingRevenue ? [
                'invoice_id'  => $pendingRevenue->xendit_invoice_id,
                'invoice_url' => $pendingRevenue->invoice_url,
                'qr_code_url' => $pendingRevenue->qr_code_url,
                'amount'      => $pendingRevenue->amount,
                'package'     => $this->resolvePackageKeyFromAmount($pendingRevenue->amount),
                'expiry_date' => $pendingRevenue->expiry_date,
            ] : null,
        ]);
    }

    /**
     * Cocokkan nominal Revenue ke key paket (monthly/yearly) di config, buat
     * ditampilkan sebagai label di frontend saat resume pending payment.
     */
    private function resolvePackageKeyFromAmount(mixed $amount): ?string
    {
        foreach (config('subscription.packages', []) as $key => $pkg) {
            if ((float) $pkg['amount'] === (float) $amount) {
                return $key;
            }
        }
        return null;
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

        // Kalau masih ada invoice PENDING & belum expired untuk paket yang
        // SAMA, pakai ulang itu — jangan bikin invoice Xendit baru. Ini yang
        // bikin klik "X" (sengaja/tidak sengaja) lalu klik "beli paket" lagi
        // langsung nampilin pembayaran yang sama, bukan mulai dari nol.
        $existing = Revenue::where('user_id', $student->id)
            ->where('payment_status', 'pending')
            ->where('amount', $package['amount'])
            ->where('expiry_date', '>', now())
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                'message'     => 'Melanjutkan invoice pembayaran yang sudah ada.',
                'invoice_id'  => $existing->xendit_invoice_id,
                'invoice_url' => $existing->invoice_url,
                'qr_code_url' => $existing->qr_code_url,
                'amount'      => $existing->amount,
                'package'     => $validated['package'],
                'expiry_date' => $existing->expiry_date,
                'resumed'     => true,
            ], 200);
        }

        // Ada pending invoice tapi buat PAKET LAIN (mis. sebelumnya pilih
        // Monthly, sekarang klik Yearly) — tandai yang lama sebagai gugur,
        // baru lanjut bikin invoice fresh untuk paket yang baru dipilih.
        Revenue::where('user_id', $student->id)
            ->where('payment_status', 'pending')
            ->update(['payment_status' => 'failed']);

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

        // Store invoice details on revenue + subscription. invoice_url/qr_code_url
        // WAJIB disimpan (bukan cuma dikirim di response sekali ini) supaya bisa
        // di-resume nanti kalau user tutup modal sebelum bayar.
        $revenue->update([
            'xendit_invoice_id' => $invoice['id'],
            'invoice_url'       => $invoice['invoice_url'],
            'qr_code_url'       => $invoice['qr_code_url'],
            'expiry_date'       => $invoice['expiry_date'] ?? null,
        ]);
        $subscription->update(['xendit_invoice_id' => $invoice['id']]);

        return response()->json([
            'message'     => 'Invoice berhasil dibuat.',
            'invoice_id'  => $invoice['id'],
            'invoice_url' => $invoice['invoice_url'],
            'qr_code_url' => $invoice['qr_code_url'],
            'amount'      => $package['amount'],
            'package'     => $validated['package'],
            'expiry_date' => $invoice['expiry_date'] ?? null,
            'resumed'     => false,
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

    // ── POST /api/v1/subscriptions/cancel ──────────────────────────

    /**
     * Cancel an active or pending subscription for a student.
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
        ]);

        $authUser = $request->user();
        $isOwner  = $authUser?->student?->id === $validated['student_id'];
        $isAdmin  = $authUser?->role === 'super_admin';

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki hak untuk membatalkan langganan siswa ini.',
            ], 403);
        }

        $student = Student::with('subscription')->findOrFail($validated['student_id']);

        // Update student status to free
        $student->update([
            'status_subscription'     => 'free',
            'subscription_renewed_at' => null,
        ]);

        // Cancel subscription record if exists
        if ($student->subscription) {
            $student->subscription->update([
                'status' => 'cancelled',
            ]);
        }

        // Cancel any pending payment revenues
        Revenue::where('user_id', $student->id)
            ->where('payment_status', 'pending')
            ->update(['payment_status' => 'failed']);

        return response()->json([
            'message' => 'Paket langganan Premium berhasil dibatalkan.',
            'data'    => [
                'student_id'          => $student->id,
                'status_subscription' => 'free',
            ]
        ]);
    }
}
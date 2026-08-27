<?php

namespace App\Http\Controllers;

use App\Models\Revenue;
use App\Models\Student;
use App\Models\Subscription;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(protected MidtransService $paymentGateway) {}

    // ── GET /api/v1/subscriptions/user/{userId} ────────────────────────────

    /**
     * Fetch the current subscription status for a student or company.
     */
    public function getUserSubscription(Request $request, string $userId): JsonResponse
    {
        $authUser = $request->user();
        $isOwnerStudent = $authUser?->student?->id === $userId || $authUser?->id === $userId;
        $isOwnerCompany = $authUser?->company?->id === $userId;
        $isOwnerSchool  = $authUser?->school?->id === $userId;
        $isAdmin        = $authUser?->role === 'super_admin';

        if (!$isOwnerStudent && !$isOwnerCompany && !$isOwnerSchool && !$isAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki akses ke data langganan ini.',
            ], 403);
        }

        // Cek jika ID adalah School
        $school = \App\Models\School::where('id', $userId)->orWhere('user_id', $userId)->first();
        if ($school && ($isOwnerSchool || $isAdmin || $authUser?->school?->id === $school->id)) {
            $subscription = Subscription::where('user_id', $school->id)->first();
            $pendingRevenue = Revenue::where('user_id', $school->id)
                ->where('payment_status', 'pending')
                ->where('expiry_date', '>', now())
                ->latest()
                ->first();

            return response()->json([
                'student_id'              => $school->id,
                'school_id'               => $school->id,
                'company_id'              => null,
                'status_subscription'     => $school->status_subscription ?? 'free',
                'subscription_renewed_at' => $school->subscription_renewed_at,
                'subscription'            => $school->status_subscription === 'premium' ? [
                    'id'                      => $subscription?->id ?? 1,
                    'status'                  => 'active',
                    'amount'                  => $subscription?->amount ?? 0,
                    'currency'                => 'IDR',
                    'subscription_start_date' => $school->subscription_renewed_at,
                    'subscription_end_date'   => $school->subscription_renewed_at ? \Carbon\Carbon::parse($school->subscription_renewed_at)->addYear() : null,
                    'renewal_date'            => null,
                    'is_expired'              => false,
                    'is_renewal_due'          => false,
                ] : null,
                'pending_payment' => $pendingRevenue ? [
                    'invoice_id'  => $pendingRevenue->payment_reference_id,
                    'invoice_url' => $pendingRevenue->invoice_url,
                    'qr_code_url' => $pendingRevenue->qr_code_url,
                    'amount'      => $pendingRevenue->amount,
                    'package'     => $this->resolvePackageKeyFromAmount($pendingRevenue->amount),
                    'expiry_date' => $pendingRevenue->expiry_date,
                ] : null,
            ]);
        }

        // Cek jika ID adalah Company
        $company = \App\Models\Company::where('id', $userId)->orWhere('user_id', $userId)->first();
        if ($company && ($isOwnerCompany || $isAdmin || $authUser?->company?->id === $company->id)) {
            $subscription = Subscription::where('user_id', $company->id)->first();
            $pendingRevenue = Revenue::where('user_id', $company->id)
                ->where('payment_status', 'pending')
                ->where('expiry_date', '>', now())
                ->latest()
                ->first();

            return response()->json([
                'student_id'              => $company->id,
                'company_id'              => $company->id,
                'status_subscription'     => $company->status_subscription ?? 'free',
                'subscription_renewed_at' => $company->subscription_renewed_at,
                'subscription'            => $company->status_subscription === 'premium' ? [
                    'id'                      => $subscription?->id ?? 1,
                    'status'                  => 'active',
                    'amount'                  => $subscription?->amount ?? 0,
                    'currency'                => 'IDR',
                    'subscription_start_date' => $company->subscription_renewed_at,
                    'subscription_end_date'   => $company->subscription_renewed_at ? \Carbon\Carbon::parse($company->subscription_renewed_at)->addYear() : null,
                    'renewal_date'            => null,
                    'is_expired'              => false,
                    'is_renewal_due'          => false,
                ] : null,
                'pending_payment' => $pendingRevenue ? [
                    'invoice_id'  => $pendingRevenue->payment_reference_id,
                    'invoice_url' => $pendingRevenue->invoice_url,
                    'qr_code_url' => $pendingRevenue->qr_code_url,
                    'amount'      => $pendingRevenue->amount,
                    'package'     => $this->resolvePackageKeyFromAmount($pendingRevenue->amount),
                    'expiry_date' => $pendingRevenue->expiry_date,
                ] : null,
            ]);
        }

        $student = Student::where('id', $userId)
            ->with(['subscription', 'school'])
            ->firstOrFail();

        $subscription = $student->subscription;

        // Invoice pending & belum expired (kalau ada)
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
                'invoice_id'  => $pendingRevenue->payment_reference_id,
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
     * Create a Midtrans QRIS payment for a premium subscription upgrade.
     */
    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'nullable|uuid',
            'company_id' => 'nullable|uuid',
            'school_id'  => 'nullable|uuid',
            'package'    => 'required|in:monthly,yearly',
        ]);

        $authUser  = $request->user();
        $companyId = $validated['company_id'] ?? null;
        $schoolId  = $validated['school_id'] ?? null;
        $studentId = $validated['student_id'] ?? null;

        if ($schoolId) {
            $school = \App\Models\School::findOrFail($schoolId);
            $isOwner = $authUser?->school?->id === $school->id;
            $isAdmin = $authUser?->role === 'super_admin';

            if (!$isOwner && !$isAdmin) {
                return response()->json([
                    'errors' => 'Anda tidak bisa membuat pembayaran untuk akun sekolah lain.',
                ], 403);
            }

            $packages = config('subscription.packages');
            $package  = $packages[$validated['package']];

            $existing = Revenue::where('user_id', $school->id)
                ->where('payment_status', 'pending')
                ->where('amount', $package['amount'])
                ->where('expiry_date', '>', now())
                ->latest()
                ->first();

            if ($existing) {
                return response()->json([
                    'message'     => 'Melanjutkan invoice pembayaran yang sudah ada.',
                    'invoice_id'  => $existing->payment_reference_id,
                    'invoice_url' => $existing->invoice_url,
                    'qr_code_url' => $existing->qr_code_url,
                    'amount'      => $existing->amount,
                    'package'     => $validated['package'],
                    'expiry_date' => $existing->expiry_date,
                    'resumed'     => true,
                ], 200);
            }

            Revenue::where('user_id', $school->id)
                ->where('payment_status', 'pending')
                ->update(['payment_status' => 'failed']);

            $now          = now();
            $endDate      = $now->copy()->addDays($package['duration_days']);
            $renewalDate  = $now->copy()->addDays($package['duration_days']);
            $referenceId  = 'SUB-SCH-' . strtoupper(Str::random(10)) . '-' . substr($school->id, 0, 8);

            $subscription = Subscription::updateOrCreate(
                ['user_id' => $school->id],
                [
                    'user_type'               => 'school',
                    'amount'                  => $package['amount'],
                    'currency'                => 'IDR',
                    'status'                  => 'pending_payment',
                    'subscription_start_date' => $now,
                    'subscription_end_date'   => $endDate,
                    'renewal_date'            => $renewalDate,
                    'payment_method'          => 'QRIS',
                    'external_id'             => $referenceId,
                ]
            );

            $revenue = Revenue::create([
                'subscription_id'  => $subscription->id,
                'user_id'          => $school->id,
                'user_type'        => 'school',
                'amount'           => $package['amount'],
                'currency'         => 'IDR',
                'payment_status'   => 'pending',
                'period_start'     => $now,
                'period_end'       => $endDate,
                'external_id'      => $referenceId,
            ]);

            try {
                $invoice = $this->paymentGateway->createInvoice(
                    $package['amount'],
                    $school,
                    $referenceId,
                    $package['name']
                );
            } catch (\Throwable $e) {
                $revenue->delete();
                Log::error('[SubscriptionController] school createInvoice failed: ' . $e->getMessage());
                return response()->json([
                    'errors' => 'Gagal membuat invoice pembayaran. Coba lagi.',
                    'debug'  => $e->getMessage(),
                ], 500);
            }

            $revenue->update([
                'payment_reference_id'    => $invoice['id'],
                'qr_payment_reference_id' => $invoice['qr_order_id'] ?? null,
                'invoice_url'             => $invoice['invoice_url'],
                'qr_code_url'             => $invoice['qr_code_url'],
                'expiry_date'             => $invoice['expiry_date'] ?? null,
            ]);
            $subscription->update(['payment_reference_id' => $invoice['id']]);

            return response()->json([
                'invoice_id'  => $invoice['id'],
                'invoice_url' => $invoice['invoice_url'],
                'qr_code_url' => $invoice['qr_code_url'],
                'amount'      => $invoice['amount'],
                'package'     => $validated['package'],
                'expiry_date' => $invoice['expiry_date'] ?? null,
            ], 201);
        }

        if ($companyId) {
            $company = \App\Models\Company::findOrFail($companyId);
            $isOwner = $authUser?->company?->id === $company->id;
            $isAdmin = $authUser?->role === 'super_admin';

            if (!$isOwner && !$isAdmin) {
                return response()->json([
                    'errors' => 'Anda tidak bisa membuat pembayaran untuk akun perusahaan lain.',
                ], 403);
            }

            $packages = config('subscription.packages');
            $package  = $packages[$validated['package']];

            $existing = Revenue::where('user_id', $company->id)
                ->where('payment_status', 'pending')
                ->where('amount', $package['amount'])
                ->where('expiry_date', '>', now())
                ->latest()
                ->first();

            if ($existing) {
                return response()->json([
                    'message'     => 'Melanjutkan invoice pembayaran yang sudah ada.',
                    'invoice_id'  => $existing->payment_reference_id,
                    'invoice_url' => $existing->invoice_url,
                    'qr_code_url' => $existing->qr_code_url,
                    'amount'      => $existing->amount,
                    'package'     => $validated['package'],
                    'expiry_date' => $existing->expiry_date,
                    'resumed'     => true,
                ], 200);
            }

            Revenue::where('user_id', $company->id)
                ->where('payment_status', 'pending')
                ->update(['payment_status' => 'failed']);

            $now          = now();
            $endDate      = $now->copy()->addDays($package['duration_days']);
            $renewalDate  = $now->copy()->addDays($package['duration_days']);
            $referenceId  = 'SUB-COMP-' . strtoupper(Str::random(10)) . '-' . substr($company->id, 0, 8);

            $subscription = Subscription::updateOrCreate(
                ['user_id' => $company->id],
                [
                    'user_type'               => 'company',
                    'amount'                  => $package['amount'],
                    'currency'                => 'IDR',
                    'status'                  => 'pending_payment',
                    'subscription_start_date' => $now,
                    'subscription_end_date'   => $endDate,
                    'renewal_date'            => $renewalDate,
                    'payment_method'          => 'QRIS',
                    'external_id'             => $referenceId,
                ]
            );

            $revenue = Revenue::create([
                'subscription_id'  => $subscription->id,
                'user_id'          => $company->id,
                'user_type'        => 'company',
                'amount'           => $package['amount'],
                'currency'         => 'IDR',
                'payment_status'   => 'pending',
                'period_start'     => $now,
                'period_end'       => $endDate,
                'external_id'      => $referenceId,
            ]);

            try {
                $invoice = $this->paymentGateway->createInvoice(
                    $package['amount'],
                    $company,
                    $referenceId,
                    $package['name']
                );
            } catch (\Throwable $e) {
                $revenue->delete();
                Log::error('[SubscriptionController] company createInvoice failed: ' . $e->getMessage());
                return response()->json([
                    'errors' => 'Gagal membuat invoice pembayaran. Coba lagi.',
                    'debug'  => $e->getMessage(),
                ], 500);
            }

            $revenue->update([
                'payment_reference_id'    => $invoice['id'],
                'qr_payment_reference_id' => $invoice['qr_order_id'] ?? null,
                'invoice_url'             => $invoice['invoice_url'],
                'qr_code_url'             => $invoice['qr_code_url'],
                'expiry_date'             => $invoice['expiry_date'] ?? null,
            ]);
            $subscription->update(['payment_reference_id' => $invoice['id']]);

            return response()->json([
                'invoice_id'  => $invoice['id'],
                'invoice_url' => $invoice['invoice_url'],
                'qr_code_url' => $invoice['qr_code_url'],
                'amount'      => $invoice['amount'],
                'package'     => $validated['package'],
                'expiry_date' => $invoice['expiry_date'] ?? null,
            ], 201);
        }

        $studentId = $studentId ?? $validated['student_id'];
        if (!$studentId) {
            return response()->json(['errors' => 'ID Akun wajib diisi.'], 422);
        }

        $isOwner  = $authUser?->student?->id === $studentId;
        $isAdmin  = $authUser?->role === 'super_admin';

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'errors' => 'Anda tidak bisa membuat pembayaran untuk akun siswa lain.',
            ], 403);
        }

        $student  = Student::with('school')->findOrFail($studentId);
        $packages = config('subscription.packages');
        $package  = $packages[$validated['package']];

        $existing = Revenue::where('user_id', $student->id)
            ->where('payment_status', 'pending')
            ->where('amount', $package['amount'])
            ->where('expiry_date', '>', now())
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                'message'     => 'Melanjutkan invoice pembayaran yang sudah ada.',
                'invoice_id'  => $existing->payment_reference_id,
                'invoice_url' => $existing->invoice_url,
                'qr_code_url' => $existing->qr_code_url,
                'amount'      => $existing->amount,
                'package'     => $validated['package'],
                'expiry_date' => $existing->expiry_date,
                'resumed'     => true,
            ], 200);
        }

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
                'payment_method'          => 'QRIS',
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
            $invoice = $this->paymentGateway->createInvoice(
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
            'payment_reference_id'    => $invoice['id'],
            'qr_payment_reference_id' => $invoice['qr_order_id'] ?? null,
            'invoice_url'             => $invoice['invoice_url'],
            'qr_code_url'             => $invoice['qr_code_url'],
            'expiry_date'             => $invoice['expiry_date'] ?? null,
        ]);
        $subscription->update(['payment_reference_id' => $invoice['id']]);

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

        $revenue = Revenue::where('payment_reference_id', $invoiceId)
            ->orWhere('qr_payment_reference_id', $invoiceId)
            ->first();

        if (!$isAdmin) {
            $isOwner = $revenue && $authUser?->student?->id === $revenue->user_id;

            if (!$isOwner) {
                return response()->json([
                    'errors' => 'Anda tidak memiliki akses ke invoice ini.',
                ], 403);
            }
        }

        if (!$revenue) {
            return response()->json(['errors' => 'Invoice tidak ditemukan.'], 404);
        }

        try {
            // Cek KEDUA transaksi (QR + Snap) sekaligus — siswa bisa bayar
            // lewat salah satu, bukan cuma yang order_id-nya persis dikirim
            // di URL ini.
            $status = $this->paymentGateway->getCombinedStatus($revenue);
        } catch (\Throwable $e) {
            return response()->json(['errors' => 'Gagal memeriksa status pembayaran.'], 502);
        }

        // Fallback kalau webhook Midtrans belum/gagal nyampe: begitu polling
        // ini lihat status-nya sudah final (PAID/SETTLED atau
        // EXPIRED/FAILED), langsung jalankan proses yang sama seperti yang
        // dilakukan webhook. Idempotent, jadi aman kalau ternyata webhook-nya
        // nyampe duluan atau menyusul.
        if ($status['paid']) {
            $this->paymentGateway->confirmPayment($status['id'] ?? $invoiceId, $revenue->external_id);
        } elseif (in_array($status['status'], ['EXPIRED', 'FAILED'])) {
            $this->paymentGateway->markExpired($invoiceId);
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
            'student_id' => 'nullable|uuid',
            'company_id' => 'nullable|uuid',
            'school_id'  => 'nullable|uuid',
        ]);

        $authUser  = $request->user();
        $companyId = $validated['company_id'] ?? null;
        $schoolId  = $validated['school_id'] ?? null;
        $studentId = $validated['student_id'] ?? null;

        if ($schoolId) {
            $school  = \App\Models\School::findOrFail($schoolId);
            $isOwner = $authUser?->school?->id === $school->id;
            $isAdmin = $authUser?->role === 'super_admin';

            if (!$isOwner && !$isAdmin) {
                return response()->json([
                    'errors' => 'Anda tidak memiliki hak untuk membatalkan langganan sekolah ini.',
                ], 403);
            }

            $school->update([
                'status_subscription'     => 'free',
                'subscription_renewed_at' => null,
            ]);

            if ($school->user_id) {
                \App\Models\User::where('id', $school->user_id)->update(['is_pro' => false]);
            }

            Subscription::where('user_id', $school->id)->where('status', 'active')->update(['status' => 'expired']);

            Revenue::where('user_id', $school->id)
                ->where('payment_status', 'pending')
                ->update(['payment_status' => 'failed']);

            return response()->json([
                'message' => 'Paket langganan Premium sekolah berhasil dibatalkan.',
                'data'    => [
                    'school_id'           => $school->id,
                    'status_subscription' => 'free',
                ]
            ]);
        }

        if ($companyId) {
            $company = \App\Models\Company::findOrFail($companyId);
            $isOwner = $authUser?->company?->id === $company->id;
            $isAdmin = $authUser?->role === 'super_admin';

            if (!$isOwner && !$isAdmin) {
                return response()->json([
                    'errors' => 'Anda tidak memiliki hak untuk membatalkan langganan perusahaan ini.',
                ], 403);
            }

            $company->update([
                'status_subscription'     => 'free',
                'subscription_renewed_at' => null,
            ]);

            if ($company->user_id) {
                \App\Models\User::where('id', $company->user_id)->update(['is_pro' => false]);
            }

            Subscription::where('user_id', $company->id)->where('status', 'active')->update(['status' => 'expired']);

            Revenue::where('user_id', $company->id)
                ->where('payment_status', 'pending')
                ->update(['payment_status' => 'failed']);

            return response()->json([
                'message' => 'Paket langganan Premium perusahaan berhasil dibatalkan.',
                'data'    => [
                    'company_id'          => $company->id,
                    'status_subscription' => 'free',
                ]
            ]);
        }

        $studentId = $studentId ?? $validated['student_id'];
        if (!$studentId) {
            return response()->json(['errors' => 'ID Akun wajib diisi.'], 422);
        }

        $isOwner  = $authUser?->student?->id === $studentId;
        $isAdmin  = $authUser?->role === 'super_admin';

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'errors' => 'Anda tidak memiliki hak untuk membatalkan langganan siswa ini.',
            ], 403);
        }

        $student = Student::with('subscription')->findOrFail($studentId);

        // Update student status to free
        $student->update([
            'status_subscription'     => 'free',
            'subscription_renewed_at' => null,
        ]);

        if ($student->user_id) {
            \App\Models\User::where('id', $student->user_id)->update(['is_pro' => false]);
        }

        // Mark subscription record as expired if exists
        if ($student->subscription) {
            $student->subscription->update([
                'status' => 'expired',
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
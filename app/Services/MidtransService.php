<?php

namespace App\Services;

use App\Models\Revenue;
use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pengganti XenditService — pakai Midtrans Core API (payment_type: qris)
 * supaya modal QR custom kita tetap bisa dipakai persis kayak sebelumnya
 * (bukan Snap yang redirect ke halaman Midtrans sendiri).
 *
 * SENGAJA meniru method publik XenditService satu-satu (createInvoice,
 * getInvoiceStatus, handleWebhook, confirmPayment, markExpired,
 * sweepExpiredPending) supaya SubscriptionController, WebhookController,
 * SubscriptionExpirePendingPayments, dan RevenueController cuma perlu ganti
 * type-hint constructor-nya — logic reconciliation (fallback polling,
 * idempotency, race-condition guard paid-vs-expired) TIDAK ditulis ulang,
 * cuma di-copy dari XenditService yang sudah teruji.
 *
 * Catatan teknis: kolom DB `revenue.payment_reference_id` &
 * `subscriptions.payment_reference_id` TETAP dipakai apa adanya (menyimpan
 * Midtrans order_id di situ) — sengaja tidak di-rename supaya migrasi ini
 * tidak perlu ubah struktur database sama sekali. Boleh di-rename ke nama
 * yang lebih netral (mis. `payment_reference_id`) sebagai cleanup terpisah
 * kapan-kapan kalau mau.
 */
class MidtransService
{
    /**
     * Prefix WAJIB di setiap order_id yang kita generate — akun Midtrans
     * ini dipakai bareng sama satu web lain, jadi ini yang jadi pembeda
     * "transaksi ini punya Prakerin atau bukan" (dipakai juga untuk membuang
     * webhook Midtrans yang ternyata punya web satunya — lihat
     * WebhookController::handleMidtransWebhook).
     */
    public const ORDER_ID_PREFIX = 'PRAKERIN-';

    protected string $serverKey;
    protected string $baseUrl;
    protected string $snapBaseUrl;

    public function __construct()
    {
        $this->serverKey = config('subscription.midtrans.server_key', '');
        $this->baseUrl   = config('subscription.midtrans.base_url', 'https://api.sandbox.midtrans.com');
        // Snap API pakai domain BEDA dari Core API ("app." bukan "api."),
        // walau server key & mode production/sandbox-nya sama persis.
        $this->snapBaseUrl = str_contains($this->baseUrl, 'sandbox')
            ? 'https://app.sandbox.midtrans.com'
            : 'https://app.midtrans.com';
    }

    /**
     * Cek apakah sebuah order_id itu punya Prakerin (vs punya web lain yang
     * berbagi akun Midtrans yang sama).
     */
    public static function belongsToThisApp(?string $orderId): bool
    {
        return $orderId !== null && str_starts_with($orderId, self::ORDER_ID_PREFIX);
    }

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Bikin transaksi QRIS lewat Midtrans Core API.
     *
     * @param  float   $amount       Amount in IDR (e.g. 99000)
     * @param  Student $student      The paying student
     * @param  string  $referenceId  Reference internal kita (disimpan sebagai
     *                               `external_id` di tabel revenue oleh
     *                               controller — BUKAN dikirim sebagai
     *                               order_id ke Midtrans, karena referenceId
     *                               kita [prefix + random + UUID siswa] bisa
     *                               lebih dari 50 karakter, melebihi limit
     *                               order_id Midtrans. order_id sendiri kita
     *                               generate baru yang lebih pendek di sini.
     * @param  string  $description  Human-readable description
     * @return array{ id: string, invoice_url: string|null, qr_code_url: string|null, expiry_date: string, amount: float }
     */
    public function createInvoice(float $amount, Student $student, string $referenceId, string $description = 'Prakerin Premium Subscription'): array
    {
        $user = $student->user;
        $expirySeconds = config('subscription.payment_expiry_seconds', 300);

        // order_id Midtrans: maksimal 50 karakter, cuma boleh alfanumerik +
        // dash/underscore. referenceId kita ('SUB-' + random + UUID siswa)
        // bisa lebih panjang dari itu, jadi generate order_id baru yang
        // pendek & tetap unik (timestamp + random). Pencocokan record tetap
        // bisa lewat kolom `external_id` (referenceId asli) sebagai fallback
        // — lihat confirmPayment()/markExpired().
        //
        // PENTING: akun Midtrans ini dipakai BARENG sama satu web lain.
        // Prefix "PRAKERIN" di sini WAJIB ada & jangan dihapus — bukan cuma
        // estetika, tapi supaya:
        //  1. order_id kita gak collide sama punya web satunya walau mereka
        //     pakai pola generate ID yang mirip.
        //  2. Tim bisa langsung bedain transaksi mana punya Prakerin di
        //     Dashboard Midtrans yang datanya bakal ke-mix sama web lain.
        //  3. handleMidtransWebhook() bisa langsung skip notifikasi yang
        //     BUKAN dari order_id kita (lihat WebhookController).
        $orderId = self::ORDER_ID_PREFIX . now()->format('ymdHis') . strtoupper(Str::random(6));

        $customerDetails = array_filter([
            'first_name' => $student->name,
            'email'      => $user?->email,
            'phone'      => $student->phone_number ? (string) $student->phone_number : null,
        ], fn ($value) => $value !== null && $value !== '');

        $itemDetails = [[
            'id'       => 'subscription',
            'price'    => (int) round($amount),
            'quantity' => 1,
            'name'     => substr($description, 0, 50),
        ]];

        // 1) QRIS langsung lewat Core API — BEST EFFORT. Kalau channel-nya
        //    belum aktif di akun (lihat error 402 kemarin) atau gagal karena
        //    sebab lain, JANGAN gagalkan seluruh proses — cukup log & lanjut
        //    tanpa QR. order_id-nya dikasih suffix "-QR" supaya beda dari
        //    order_id Snap di bawah (Midtrans wajib unique order_id).
        $qrOrderId = null;
        $qrCodeUrl = null;

        try {
            $qrResult = $this->chargeQris($orderId . '-QR', $amount, $customerDetails, $itemDetails, $expirySeconds);
            $qrOrderId = $qrResult['order_id'];
            $qrCodeUrl = $qrResult['qr_code_url'];
        } catch (\Throwable $e) {
            Log::warning('[MidtransService] createInvoice: QRIS langsung gagal (lanjut pakai Snap saja) — ' . $e->getMessage(), [
                'ref' => $referenceId,
            ]);
        }

        // 2) Snap — WAJIB berhasil. Ini yang jadi link "bayar dengan metode
        //    lain" (Bank Transfer/VA, e-wallet, Indomaret/Alfamart, kartu
        //    kredit, dst — apapun yang aktif di akun). Kalau QRIS di akun
        //    ini SUDAH aktif, Snap juga otomatis nampilin QRIS sebagai salah
        //    satu pilihan di halamannya — jadi begitu QRIS di-approve
        //    Midtrans nanti, otomatis muncul di sini juga tanpa ubah kode.
        $snapResult = $this->createSnapTransaction($orderId, $amount, $customerDetails, $itemDetails, $expirySeconds);

        return [
            'id'          => $orderId,           // order_id Snap — dipakai sebagai payment_reference_id utama
            'qr_order_id' => $qrOrderId,          // order_id QR terpisah (null kalau QRIS gagal/belum aktif)
            'invoice_url' => $snapResult['redirect_url'],
            'qr_code_url' => $qrCodeUrl,
            'expiry_date' => now()->addSeconds($expirySeconds)->toIso8601String(),
            'amount'      => $amount,
        ];
    }

    /**
     * Charge QRIS langsung lewat Core API (best-effort — lihat createInvoice()).
     * Dipisah jadi method sendiri supaya gampang di-try/catch dari caller
     * tanpa bikin seluruh createInvoice() ikut gagal.
     *
     * @return array{order_id: string, qr_code_url: string}
     */
    private function chargeQris(string $orderId, float $amount, array $customerDetails, array $itemDetails, int $expirySeconds): array
    {
        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) round($amount),
            ],
            'item_details'      => $itemDetails,
            'customer_details'  => $customerDetails,
            'qris' => [
                'acquirer' => 'gopay',
            ],
            'custom_expiry' => [
                'expiry_duration' => $expirySeconds,
                'unit'            => 'second',
            ],
        ];

        $response = Http::withBasicAuth($this->serverKey, '')
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(30)
            ->post("{$this->baseUrl}/v2/charge", $payload);

        if ($response->failed()) {
            Log::error('[MidtransService] chargeQris failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'ref'    => $orderId,
            ]);
            throw new \RuntimeException('Failed to create Midtrans QRIS transaction: ' . $response->body());
        }

        $data = $response->json();
        $qrAction = collect($data['actions'] ?? [])->firstWhere('name', 'generate-qr-code');

        // Midtrans kadang balikin HTTP 200/201 padahal transaksinya SEBENARNYA
        // gagal (bukan approved) — bedanya cuma keliatan dari field
        // status_code/status_message DI DALAM body respons, BUKAN dari kode
        // status HTTP-nya. Contoh paling sering: channel QRIS belum diaktifkan
        // di akun (khususnya akun PRODUCTION — SANDBOX defaultnya semua
        // channel aktif).
        if (empty($qrAction['url'] ?? null)) {
            Log::warning('[MidtransService] chargeQris: HTTP sukses tapi tidak ada QR code di respons (kemungkinan channel QRIS belum aktif di akun ini)', [
                'status_code'    => $data['status_code'] ?? null,
                'status_message' => $data['status_message'] ?? null,
                'body'           => $response->body(),
                'ref'            => $orderId,
            ]);
            throw new \RuntimeException(
                'Midtrans tidak mengembalikan kode QRIS (status: ' . ($data['status_code'] ?? '?') . ' — '
                . ($data['status_message'] ?? 'pesan tidak diketahui') . ').'
            );
        }

        return [
            'order_id'    => $data['order_id'] ?? $orderId,
            'qr_code_url' => $qrAction['url'],
        ];
    }

    /**
     * Bikin transaksi Snap — halaman hosted Midtrans yang nampilin SEMUA
     * metode pembayaran yang aktif di akun (Bank Transfer/VA, e-wallet,
     * retail/Indomaret-Alfamart, kartu kredit, dst). Ini yang jadi
     * `invoice_url` / link "bayar dengan metode lain" di frontend.
     *
     * @return array{token: string, redirect_url: string}
     */
    private function createSnapTransaction(string $orderId, float $amount, array $customerDetails, array $itemDetails, int $expirySeconds): array
    {
        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) round($amount),
            ],
            'item_details'     => $itemDetails,
            'customer_details' => $customerDetails,
            'expiry' => [
                'duration' => $expirySeconds,
                'unit'     => 'second',
            ],
        ];

        $response = Http::withBasicAuth($this->serverKey, '')
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(30)
            ->post("{$this->snapBaseUrl}/snap/v1/transactions", $payload);

        if ($response->failed()) {
            Log::error('[MidtransService] createSnapTransaction failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'ref'    => $orderId,
            ]);
            throw new \RuntimeException('Failed to create Midtrans Snap transaction: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['redirect_url'] ?? null)) {
            Log::error('[MidtransService] createSnapTransaction: tidak ada redirect_url di respons', [
                'body' => $response->body(),
                'ref'  => $orderId,
            ]);
            throw new \RuntimeException('Midtrans Snap tidak mengembalikan redirect_url: ' . $response->body());
        }

        return [
            'token'        => $data['token'] ?? '',
            'redirect_url' => $data['redirect_url'],
        ];
    }

    /**
     * Cek status gabungan QR + Snap untuk satu Revenue — dipakai endpoint
     * polling (`SubscriptionController::getPaymentStatus`). Siswa bisa bayar
     * lewat SALAH SATU (QR langsung ATAU link Snap "metode lain"), jadi kalau
     * salah satunya PAID, invoice ini dianggap lunas.
     */
    public function getCombinedStatus(Revenue $revenue): array
    {
        if ($revenue->payment_status === 'paid') {
            return ['id' => $revenue->payment_reference_id, 'status' => 'PAID', 'paid' => true];
        }

        if ($revenue->qr_payment_reference_id) {
            try {
                $qrStatus = $this->getInvoiceStatus($revenue->qr_payment_reference_id);
                if ($qrStatus['paid']) {
                    return $qrStatus;
                }
            } catch (\Throwable $e) {
                Log::warning('[MidtransService] getCombinedStatus: gagal cek status QR — ' . $e->getMessage());
            }
        }

        return $this->getInvoiceStatus($revenue->payment_reference_id);
    }

    /**
     * Fetch live transaction status from Midtrans, dinormalisasi ke format
     * yang sama seperti XenditService (PENDING|PAID|EXPIRED|FAILED) supaya
     * seluruh kode pemanggil (controller, command, sweep) tidak perlu tahu
     * bedanya sama sekali.
     */
    public function getInvoiceStatus(string $invoiceId): array
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(15)
            ->get("{$this->baseUrl}/v2/{$invoiceId}/status");

        // Midtrans balikin 404 kalau order_id belum pernah ada transaksi
        // sama sekali — perlakukan sebagai PENDING (belum ada apa-apa),
        // bukan exception, supaya sweep/polling/sync gak berhenti gara-gara
        // ini. PENTING: Midtrans TIDAK KONSISTEN soal kode HTTP-nya — kadang
        // 404, kadang 400 — buat pesan yang SAMA ("Transaction doesn't
        // exist"). Jadi jangan cuma cek status()===404, cek juga ISI PESAN
        // responnya. Ini juga yang bikin sync gagal buat record LAMA dari
        // era Xendit (payment_reference_id-nya ID Xendit, emang gak akan
        // pernah ketemu di Midtrans — itu wajar, bukan bug).
        $body          = $response->json();
        $statusMessage = strtolower($body['status_message'] ?? '');
        $notFound      = $response->status() === 404
            || str_contains($statusMessage, "doesn't exist")
            || str_contains($statusMessage, 'not found');

        if ($notFound) {
            return ['id' => $invoiceId, 'status' => 'PENDING', 'paid' => false, 'not_found' => true];
        }

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch Midtrans transaction status: ' . $response->body());
        }

        $data = $response->json();

        return [
            'id'     => $data['order_id'] ?? $invoiceId,
            'status' => $this->normalizeStatus($data['transaction_status'] ?? null),
            'paid'   => in_array($data['transaction_status'] ?? null, ['settlement', 'capture']),
        ];
    }

    /**
     * Map status Midtrans (lowercase) ke format seragam yang sudah dipakai
     * di seluruh codebase kita sejak Xendit (PENDING|PAID|EXPIRED|FAILED).
     */
    private function normalizeStatus(?string $midtransStatus): string
    {
        return match ($midtransStatus) {
            'settlement', 'capture' => 'PAID',
            'expire'                => 'EXPIRED',
            'cancel', 'deny', 'failure' => 'FAILED',
            default                 => 'PENDING',
        };
    }

    /**
     * Handle an incoming Midtrans HTTP Notification payload.
     *
     * @param  array $payload  The JSON-decoded webhook body
     * @return bool            Whether the event was handled
     */
    public function handleWebhook(array $payload): bool
    {
        $orderId = $payload['order_id'] ?? null;
        $status  = $this->normalizeStatus($payload['transaction_status'] ?? null);

        if (!$orderId) {
            return false;
        }

        if ($status === 'PAID') {
            return $this->confirmPayment($orderId);
        }

        if (in_array($status, ['EXPIRED', 'FAILED'])) {
            return $this->markExpired($orderId);
        }

        return false;
    }

    /**
     * Tandai invoice sebagai lunas & upgrade student ke premium.
     *
     * Identik dengan XenditService::confirmPayment — logic reconciliation
     * (idempotent, fallback lewat external_id, upgrade subscription+student,
     * kirim notifikasi) di-copy apa adanya karena provider-agnostic, cuma
     * dipanggil dengan Midtrans order_id sebagai $invoiceId.
     */
    public function confirmPayment(?string $invoiceId, ?string $externalId = null): bool
    {
        if (!$invoiceId && !$externalId) {
            return false;
        }

        $revenue = Revenue::where(function ($q) use ($invoiceId, $externalId) {
            if ($invoiceId) {
                $q->where('payment_reference_id', $invoiceId)
                  ->orWhere('qr_payment_reference_id', $invoiceId);
            }
            if ($externalId) {
                $q->orWhere('external_id', $externalId);
            }
        })->first();

        if (!$revenue) {
            Log::warning("[MidtransService] confirmPayment: unknown order_id={$invoiceId} external_id={$externalId}");
            return false;
        }

        if ($invoiceId && !$revenue->payment_reference_id) {
            $revenue->payment_reference_id = $invoiceId;
            $revenue->save();
        }

        if ($revenue->payment_status === 'paid') {
            return true;
        }

        $revenue->update([
            'payment_status' => 'paid',
            'payment_date'   => now(),
        ]);

        $subscription = $revenue->subscription;
        $periodStart  = $revenue->period_start ?? now();
        $periodEnd    = $revenue->period_end ?? now()->addDays(30);
        $durationDays = (int) max(1, round($periodStart->diffInDays($periodEnd)));

        // If subscription is new/pending_payment or already expired, set start & end from revenue period
        if (!$subscription || $subscription->status !== 'active' || $subscription->isExpired()) {
            $newStartDate   = $periodStart;
            $newEndDate     = $periodEnd;
            $newRenewalDate = $periodEnd;
        } else {
            // Subscription is currently active; extend from current end date
            $newStartDate   = $subscription->subscription_start_date;
            $newEndDate     = $subscription->subscription_end_date->copy()->addDays($durationDays);
            $newRenewalDate = $newEndDate;
        }

        if ($subscription) {
            $subscription->update([
                'status'                => 'active',
                'subscription_start_date' => $newStartDate,
                'subscription_end_date'   => $newEndDate,
                'renewal_date'          => $newRenewalDate,
            ]);
        }

        $userId = $subscription?->user_id ?? $revenue->user_id;
        $student = Student::find($userId);
        if ($student) {
            $student->update([
                'status_subscription'     => 'premium',
                'subscription_renewed_at' => now(),
            ]);

            if ($student->user_id) {
                \App\Models\User::where('id', $student->user_id)->update(['is_pro' => true]);
            }
        } else {
            $company = \App\Models\Company::find($userId);
            if ($company) {
                $company->update([
                    'status_subscription'     => 'premium',
                    'subscription_renewed_at' => now(),
                ]);

                if ($company->user_id) {
                    \App\Models\User::where('id', $company->user_id)->update(['is_pro' => true]);
                }
            } else {
                $school = \App\Models\School::find($userId);
                if ($school) {
                    $school->update([
                        'status_subscription'     => 'premium',
                        'subscription_renewed_at' => now(),
                    ]);

                    if ($school->user_id) {
                        \App\Models\User::where('id', $school->user_id)->update(['is_pro' => true]);
                    }
                }
            }
        }

        Log::info("[MidtransService] Payment confirmed for order_id={$invoiceId}, user={$userId}");

        try {
            $targetUserId = $student?->user_id ?? ($company?->user_id ?? ($school?->user_id ?? null));
            if ($targetUserId) {
                app(\App\Services\NotificationService::class)->notify(
                    $targetUserId,
                    '🎉 Pembayaran Berhasil!',
                    'Akun Premium kamu sudah aktif. Nikmati semua fitur premium Prakerin!',
                    'subscription_payment_confirmed',
                    config('app.frontend_url') . '/dashboard',
                    'Subscription',
                    $subscription?->id,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[MidtransService] Failed to send payment confirmation notification: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Tandai invoice sebagai gagal/kedaluwarsa. Identik dengan
     * XenditService::markExpired.
     */
    public function markExpired(?string $invoiceId, ?string $externalId = null): bool
    {
        if (!$invoiceId && !$externalId) {
            return false;
        }

        $revenue = Revenue::where(function ($q) use ($invoiceId, $externalId) {
            if ($invoiceId) {
                $q->where('payment_reference_id', $invoiceId)
                  ->orWhere('qr_payment_reference_id', $invoiceId);
            }
            if ($externalId) {
                $q->orWhere('external_id', $externalId);
            }
        })->first();

        if (!$revenue) {
            Log::warning("[MidtransService] markExpired: unknown order_id={$invoiceId} external_id={$externalId}");
            return false;
        }

        if ($revenue->payment_status !== 'pending') {
            return true;
        }

        // Yang expired cuma transaksi QR-nya (bukan Snap/order_id utama) —
        // JANGAN gugurkan seluruh invoice, karena siswa masih bisa
        // menyelesaikan pembayaran lewat link Snap ("metode lain"). Cukup
        // hapus opsi QR-nya dari tampilan.
        $isOnlyQrExpiring = $invoiceId
            && $revenue->qr_payment_reference_id === $invoiceId
            && $revenue->payment_reference_id !== $invoiceId;

        if ($isOnlyQrExpiring) {
            $revenue->update([
                'qr_payment_reference_id' => null,
                'qr_code_url'             => null,
            ]);
            Log::info("[MidtransService] QR-only expired for revenue={$revenue->id}, Snap masih berlaku.");
            return true;
        }

        $revenue->update([
            'payment_status' => 'failed',
            'notes' => 'Invoice kedaluwarsa — tidak dibayar dalam batas waktu (' . now()->toDateTimeString() . ').',
        ]);

        $subscription = $revenue->subscription;
        if ($subscription && $subscription->status === 'pending_payment') {
            $subscription->update(['status' => 'expired']);
        }

        $student = $subscription ? Student::find($subscription->user_id) : Student::find($revenue->user_id);
        if ($student && $student->status_subscription !== 'premium' && $student->user_id) {
            \App\Models\User::where('id', $student->user_id)->update(['is_pro' => false]);
        }

        Log::info("[MidtransService] Payment expired for order_id={$invoiceId}, revenue={$revenue->id}");

        try {
            if ($student && $student->user_id) {
                app(\App\Services\NotificationService::class)->notify(
                    $student->user_id,
                    '⏰ Waktu Pembayaran Habis',
                    'Invoice pembayaran premium kamu sudah kedaluwarsa. Silakan buat pembayaran baru untuk melanjutkan upgrade.',
                    'subscription_payment_expired',
                    config('app.frontend_url') . '/dashboard/profile',
                    'Subscription',
                    $subscription?->id,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[MidtransService] Failed to send payment expired notification: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Identik dengan XenditService::sweepExpiredPending.
     */
    public function sweepExpiredPending(): array
    {
        $bufferSeconds = 30;
        $expirySeconds = config('subscription.payment_expiry_seconds', 300);
        $cutoff        = now()->subSeconds($expirySeconds + $bufferSeconds);

        $pending = Revenue::where('payment_status', 'pending')
            ->whereNotNull('payment_reference_id')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $expired = 0;
        $saved   = 0;

        foreach ($pending as $revenue) {
            try {
                // Cek transaksi QR dulu (kalau ada) — siswa mungkin bayar
                // lewat QR langsung, bukan lewat link Snap.
                if ($revenue->qr_payment_reference_id) {
                    $qrStatus = $this->getInvoiceStatus($revenue->qr_payment_reference_id);
                    if ($qrStatus['paid']) {
                        $this->confirmPayment($revenue->qr_payment_reference_id, $revenue->external_id);
                        $saved++;
                        continue;
                    }
                }

                $status = $this->getInvoiceStatus($revenue->payment_reference_id);

                if ($status['paid']) {
                    $this->confirmPayment($revenue->payment_reference_id, $revenue->external_id);
                    $saved++;
                    continue;
                }
            } catch (\Throwable $e) {
                Log::warning("[MidtransService] sweepExpiredPending: failed to check invoice for revenue #{$revenue->id}: " . $e->getMessage());
            }

            // Bersihin opsi QR dulu (kalau ada) sebelum menggugurkan invoice
            // utamanya — markExpired() otomatis tahu bedain "cuma QR yang
            // expired" vs "invoice utama yang expired" lewat parameter ini.
            if ($revenue->qr_payment_reference_id) {
                $this->markExpired($revenue->qr_payment_reference_id, null);
            }
            $this->markExpired($revenue->payment_reference_id, $revenue->external_id);
            $expired++;
        }

        return ['checked' => $pending->count(), 'expired' => $expired, 'saved' => $saved];
    }

    /**
     * Verifikasi signature webhook Midtrans.
     *
     * BEDA TOTAL dari Xendit: Midtrans TIDAK pakai token statis di header.
     * Signature-nya per-notifikasi, dihitung SHA512 dari
     * order_id + status_code + gross_amount + ServerKey, lalu dikirim
     * Midtrans sebagai field `signature_key` DI DALAM body notifikasi itu
     * sendiri (bukan header).
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        $orderId     = $payload['order_id'] ?? '';
        $statusCode  = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signature   = $payload['signature_key'] ?? '';

        if (empty($this->serverKey) || empty($signature)) {
            Log::warning('[MidtransService] Missing server key or signature — rejecting webhook.');
            return false;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        return hash_equals($expected, $signature);
    }
}
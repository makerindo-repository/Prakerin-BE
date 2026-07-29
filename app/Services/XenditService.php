<?php

namespace App\Services;

use App\Models\Revenue;
use App\Models\Student;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditService
{
    protected string $secretKey;
    protected string $baseUrl;
    protected ?string $webhookToken;

    public function __construct()
    {
        $this->secretKey    = config('subscription.xendit.secret_key', '');
        $this->baseUrl      = config('subscription.xendit.base_url', 'https://api.xendit.co');
        $this->webhookToken = config('subscription.xendit.webhook_token');
    }

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Create a Xendit Invoice for a subscription payment.
     *
     * @param  float   $amount       Amount in IDR (e.g. 99000)
     * @param  Student $student      The paying student
     * @param  string  $referenceId  Unique external_id for idempotency
     * @param  string  $description  Human-readable description
     * @return array{ id: string, invoice_url: string, qr_code_url: string|null }
     */
    public function createInvoice(float $amount, Student $student, string $referenceId, string $description = 'Prakerin Premium Subscription'): array
    {
        $user = $student->user;

        $payload = [
            'external_id'     => $referenceId,
            'amount'          => $amount,
            'description'     => $description,
            'payer_email'     => $user?->email ?? 'noreply@makerindo.id',
            // array_filter membuang key yang value-nya null/kosong — Xendit menolak
            // (400 API_VALIDATION_ERROR) kalau sebuah field dikirim eksplisit null,
            // jadi field opsional yang belum diisi siswa (mis. phone_number) harus
            // dihilangkan sama sekali, bukan dikirim sebagai null.
            'customer'        => array_filter([
                'given_names'  => $student->name,
                'email'        => $user?->email,
                'phone_number' => $student->phone_number ? (string) $student->phone_number : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'currency'        => 'IDR',
            // Batas waktu bayar (detik) — begitu lewat, Xendit otomatis
            // menandai invoice ini EXPIRED, dan itu yang dideteksi oleh
            // polling/webhook untuk menandai pembayaran gagal/kedaluwarsa
            // di sistem kita (lihat markExpired()).
            'invoice_duration' => config('subscription.payment_expiry_seconds', 300),
            'success_redirect_url' => config('app.frontend_url') . '/dashboard?payment=success',
            'failure_redirect_url' => config('app.frontend_url') . '/dashboard?payment=failed',
        ];

        // Batasi metode pembayaran HANYA kalau memang dikonfigurasi (mis. sudah
        // approved QRIS di akun Xendit). Kalau kosong, kita SENGAJA tidak kirim
        // key ini sama sekali supaya Xendit menampilkan metode apapun yang
        // sudah aktif di akun (Virtual Account, e-wallet test, dll) — channel
        // ini bisa langsung dipakai tanpa perlu approval, beda dengan QRIS yang
        // butuh aktivasi ~5 hari kerja / kontak Account Manager Xendit dulu.
        // Ubah XENDIT_PAYMENT_METHODS di .env / Settings kalau QRIS sudah aktif.
        $paymentMethods = config('subscription.xendit.payment_methods', []);
        if (!empty($paymentMethods)) {
            $payload['payment_methods'] = $paymentMethods;
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(30)
            ->post("{$this->baseUrl}/v2/invoices", $payload);

        if ($response->failed()) {
            Log::error('[XenditService] createInvoice failed', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'ref'     => $referenceId,
            ]);
            throw new \RuntimeException('Failed to create Xendit invoice: ' . $response->body());
        }

        $data = $response->json();

        return [
            'id'           => $data['id'],
            'invoice_url'  => $data['invoice_url'],
            'qr_code_url'  => $data['qr_code'] ?? null,
            'expiry_date'  => $data['expiry_date'] ?? null,
            'amount'       => $data['amount'],
        ];
    }

    /**
     * Fetch live invoice status from Xendit.
     */
    public function getInvoiceStatus(string $invoiceId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(15)
            ->get("{$this->baseUrl}/v2/invoices/{$invoiceId}");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch invoice status: ' . $response->body());
        }

        $data = $response->json();

        return [
            'id'     => $data['id'],
            'status' => $data['status'], // PENDING | PAID | EXPIRED | SETTLED
            'paid'   => in_array($data['status'], ['PAID', 'SETTLED']),
        ];
    }

    /**
     * Handle an incoming Xendit webhook payload.
     *
     * @param  array $payload  The JSON-decoded webhook body
     * @return bool            Whether the event was handled
     */
    public function handleWebhook(array $payload): bool
    {
        $invoiceId  = $payload['id']          ?? null;
        $externalId = $payload['external_id'] ?? null;
        $status     = $payload['status']      ?? null;

        if (!$invoiceId && !$externalId) {
            return false;
        }

        if (in_array($status, ['PAID', 'SETTLED'])) {
            return $this->confirmPayment($invoiceId, $externalId);
        }

        if (in_array($status, ['EXPIRED', 'FAILED'])) {
            return $this->markExpired($invoiceId, $externalId);
        }

        return false;
    }

    /**
     * Tandai invoice sebagai lunas & upgrade student ke premium.
     *
     * Idempotent — aman dipanggil berkali-kali untuk invoice yang sama
     * (dicek lewat `payment_status === 'paid'`).
     *
     * SENGAJA dibuat method terpisah dan reusable (bukan cuma dipanggil dari
     * webhook) — dipakai juga sebagai fallback oleh endpoint polling
     * (`SubscriptionController::getPaymentStatus`). Webhook Xendit kadang
     * gagal nyampe (URL callback belum di-set di dashboard Xendit, mati
     * jaringan sesaat, dll) — tanpa fallback ini, siswa yang sudah bayar
     * lunas di Xendit bisa selamanya gak ke-upgrade ke premium karena
     * satu-satunya jalur upgrade cuma nunggu webhook yang gak pernah datang.
     *
     * @param  string|null  $externalId  Fallback matching kalau xendit_invoice_id
     *                                   belum sempat tersimpan (mis. response
     *                                   createInvoice() gagal balik ke server
     *                                   karena timeout/crash, padahal Xendit
     *                                   sudah berhasil bikin invoice-nya).
     *
     * 1. Marks the Revenue record as paid
     * 2. Marks the Subscription as active, extends dates
     * 3. Upgrades the Student's status_subscription to 'premium'
     */
    public function confirmPayment(?string $invoiceId, ?string $externalId = null): bool
    {
        if (!$invoiceId && !$externalId) {
            return false;
        }

        // Cocokkan pakai xendit_invoice_id ATAU external_id (fallback).
        $revenue = Revenue::where(function ($q) use ($invoiceId, $externalId) {
            if ($invoiceId) {
                $q->where('xendit_invoice_id', $invoiceId);
            }
            if ($externalId) {
                $q->orWhere('external_id', $externalId);
            }
        })->first();

        if (!$revenue) {
            Log::warning("[XenditService] confirmPayment: unknown invoice={$invoiceId} external_id={$externalId}");
            return false;
        }

        // Kalau ketemunya lewat external_id (xendit_invoice_id belum sempat
        // kesimpan), lengkapi sekarang juga supaya query berikutnya konsisten.
        if ($invoiceId && !$revenue->xendit_invoice_id) {
            $revenue->xendit_invoice_id = $invoiceId;
            $revenue->save();
        }

        if ($revenue->payment_status === 'paid') {
            // Sudah pernah diproses (baik lewat webhook atau polling
            // sebelumnya) — idempotent, gak perlu diulang.
            return true;
        }

        // Update revenue record
        $revenue->update([
            'payment_status' => 'paid',
            'payment_date'   => now(),
        ]);

        // Update subscription
        $subscription = $revenue->subscription;
        $durationDays = (int) ceil($subscription->renewal_date->diffInDays($subscription->subscription_end_date));
        if ($durationDays <= 0) {
            $durationDays = 30; // fallback
        }

        $newEndDate     = $subscription->subscription_end_date->addDays($durationDays);
        $newRenewalDate = $subscription->renewal_date->addDays($durationDays);

        $subscription->update([
            'status'                => 'active',
            'subscription_end_date' => $newEndDate,
            'renewal_date'          => $newRenewalDate,
        ]);

        // Upgrade student tier
        Student::where('id', $subscription->user_id)->update([
            'status_subscription'    => 'premium',
            'subscription_renewed_at' => now(),
        ]);

        Log::info("[XenditService] Payment confirmed for invoice={$invoiceId}, student={$subscription->user_id}");

        // Send in-app notification
        try {
            $student = Student::find($subscription->user_id);
            if ($student && $student->user_id) {
                app(\App\Services\NotificationService::class)->notify(
                    $student->user_id,
                    '🎉 Pembayaran Berhasil!',
                    'Akun Premium kamu sudah aktif. Nikmati semua fitur premium Prakerin!',
                    'subscription_payment_confirmed',
                    config('app.frontend_url') . '/dashboard',
                    'Subscription',
                    $subscription->id,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[XenditService] Failed to send payment confirmation notification: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Tandai invoice sebagai gagal/kedaluwarsa (dipanggil saat Xendit
     * melaporkan status EXPIRED atau FAILED — baik lewat webhook maupun
     * fallback polling/scheduled command).
     *
     * Idempotent & aman dari race condition: kalau ternyata revenue-nya
     * SUDAH 'paid' (mis. webhook paid & polling expired datang hampir
     * bersamaan), status paid yang menang — TIDAK ditimpa jadi expired.
     */
    public function markExpired(?string $invoiceId, ?string $externalId = null): bool
    {
        if (!$invoiceId && !$externalId) {
            return false;
        }

        $revenue = Revenue::where(function ($q) use ($invoiceId, $externalId) {
            if ($invoiceId) {
                $q->where('xendit_invoice_id', $invoiceId);
            }
            if ($externalId) {
                $q->orWhere('external_id', $externalId);
            }
        })->first();

        if (!$revenue) {
            Log::warning("[XenditService] markExpired: unknown invoice={$invoiceId} external_id={$externalId}");
            return false;
        }

        if ($revenue->payment_status !== 'pending') {
            // Sudah 'paid' atau sudah pernah di-expire-kan sebelumnya —
            // idempotent, jangan ditimpa.
            return true;
        }

        // Pakai 'failed' (bukan 'expired') supaya gak perlu migration ubah
        // enum kolom payment_status — 'failed' sudah valid di semua driver
        // DB (MySQL produksi & SQLite lokal). Alasan spesifiknya (expired
        // vs gagal lainnya) dicatat di kolom `notes` yang sudah ada.
        $revenue->update([
            'payment_status' => 'failed',
            'notes' => 'Invoice kedaluwarsa — tidak dibayar dalam batas waktu (' . now()->toDateTimeString() . ').',
        ]);

        $subscription = $revenue->subscription;
        if ($subscription && $subscription->status === 'pending_payment') {
            $subscription->update(['status' => 'expired']);
        }

        Log::info("[XenditService] Payment expired for invoice={$invoiceId}, revenue={$revenue->id}");

        // Beri tahu siswa supaya gak nunggu-nunggu tanpa kejelasan.
        try {
            $student = $subscription ? Student::find($subscription->user_id) : null;
            if ($student && $student->user_id) {
                app(\App\Services\NotificationService::class)->notify(
                    $student->user_id,
                    '⏰ Waktu Pembayaran Habis',
                    'Invoice pembayaran premium kamu sudah kedaluwarsa. Silakan buat pembayaran baru untuk melanjutkan upgrade.',
                    'subscription_payment_expired',
                    config('app.frontend_url') . '/dashboard/profile',
                    'Subscription',
                    $subscription->id,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[XenditService] Failed to send payment expired notification: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Verify Xendit webhook token from request header.
     */
    public function verifyWebhookToken(string $token): bool
    {
        if (empty($this->webhookToken)) {
            Log::warning('[XenditService] No webhook token configured, skipping verification.');
            return true; // Allow through if not configured (dev mode)
        }

        return hash_equals($this->webhookToken, $token);
    }
}
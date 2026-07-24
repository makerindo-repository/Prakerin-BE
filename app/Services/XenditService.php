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
            'payment_methods' => ['QRCODE'], // QRIS only
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
            'success_redirect_url' => config('app.frontend_url') . '/dashboard?payment=success',
            'failure_redirect_url' => config('app.frontend_url') . '/dashboard?payment=failed',
        ];

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
     * On PAID/SETTLED:
     *   1. Marks the Revenue record as paid
     *   2. Marks the Subscription as active, extends dates
     *   3. Upgrades the Student's status_subscription to 'premium'
     *
     * @param  array $payload  The JSON-decoded webhook body
     * @return bool            Whether the event was handled
     */
    public function handleWebhook(array $payload): bool
    {
        $invoiceId = $payload['id']      ?? null;
        $status    = $payload['status']  ?? null;

        if (!$invoiceId || !in_array($status, ['PAID', 'SETTLED'])) {
            return false;
        }

        // Find the Revenue record
        $revenue = Revenue::where('xendit_invoice_id', $invoiceId)->first();

        if (!$revenue) {
            Log::warning("[XenditService] Webhook received for unknown invoice={$invoiceId}");
            return false;
        }

        if ($revenue->payment_status === 'paid') {
            Log::info("[XenditService] Invoice {$invoiceId} already processed, skipping.");
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
<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * POST /api/webhooks/whatsapp/status
     *
     * Receives delivery/read status callbacks from Twilio.
     * No auth — secured by validating the Twilio signature header.
     *
     * Twilio payload (form-encoded):
     *   MessageSid, MessageStatus (sent | delivered | read | failed | undelivered)
     */
    public function whatsappStatus(Request $request)
    {
        $sid    = $request->input('MessageSid');
        $status = $request->input('MessageStatus');

        if (!$sid || !$status) {
            return response('Missing fields', 400);
        }

        // Optional Signature Verification if AuthToken is set
        $authToken = \App\Models\Setting::getVal('whatsapp_api_key');
        if (!empty($authToken) && str_contains($authToken, ':')) {
            [, $authToken] = explode(':', $authToken, 2);
        }
        $authToken = $authToken ?: env('TWILIO_AUTH_TOKEN');

        if (!empty($authToken) && $request->hasHeader('X-Twilio-Signature')) {
            $signature = $request->header('X-Twilio-Signature');
            $url = $request->fullUrl();
            $postData = $request->post();
            
            ksort($postData);
            $data = $url;
            foreach ($postData as $key => $value) {
                $data .= $key . $value;
            }
            $expectedSignature = base64_encode(hash_hmac('sha1', $data, $authToken, true));

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning("[Webhook/WhatsApp] Invalid signature for SID={$sid}");
                return response('Unauthorized signature', 403);
            }
        }

        Log::info("[Webhook/WhatsApp] SID={$sid}, status={$status}");

        $log = NotificationLog::where('message_id', $sid)->first();

        if (!$log) {
            // Not tracking this SID — might be from a different app or old message
            return response('OK', 200);
        }

        $updates = ['status' => $this->mapTwilioStatus($status)];

        if ($status === 'delivered') {
            $updates['delivered_at'] = now();
        } elseif ($status === 'read') {
            $updates['read_at'] = now();
        }

        $log->update($updates);

        return response('OK', 200);
    }

    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match ($twilioStatus) {
            'sent'          => 'sent',
            'delivered'     => 'delivered',
            'read'          => 'read',
            'failed',
            'undelivered'   => 'failed',
            default         => $twilioStatus,
        };
    }

    // ─── Midtrans Webhook (HTTP Notification) ─────────────────────────────

    /**
     * POST /api/webhooks/midtrans
     *
     * Public endpoint — Midtrans calls this after a QRIS payment event.
     * Beda dari Xendit: TIDAK ada token statis di header. Setiap notifikasi
     * punya `signature_key` (SHA512) di dalam body-nya sendiri, dihitung
     * dari order_id + status_code + gross_amount + ServerKey — diverifikasi
     * lewat MidtransService::verifyWebhookSignature().
     *
     * Midtrans mengirim JSON dengan minimal: order_id, transaction_status,
     * status_code, gross_amount, signature_key.
     */
    public function handleMidtransWebhook(Request $request): \Illuminate\Http\Response
    {
        $payload = $request->json()->all();

        $midtrans = app(\App\Services\MidtransService::class);

        if (!$midtrans->verifyWebhookSignature($payload)) {
            Log::warning('[Webhook/Midtrans] Invalid signature', [
                'order_id' => $payload['order_id'] ?? null,
            ]);
            return response('Unauthorized', 403);
        }

        $orderId = $payload['order_id'] ?? null;

        // Akun Midtrans ini dipakai bareng sama satu web lain — kalau
        // order_id-nya bukan format punya kita (prefix "PRAKERIN-"), berarti
        // ini notifikasi punya web satunya yang somehow ke-hit endpoint
        // kita (mis. webhook URL sempat salah di-set ke sini). Abaikan
        // dengan aman, JANGAN diproses — tetap balas 200 supaya Midtrans
        // gak nganggep gagal & terus retry.
        if (!\App\Services\MidtransService::belongsToThisApp($orderId)) {
            Log::info('[Webhook/Midtrans] Ignoring notification — order_id bukan milik Prakerin', [
                'order_id' => $orderId,
            ]);
            return response('OK', 200);
        }

        Log::info('[Webhook/Midtrans] Received payload', [
            'order_id'           => $orderId,
            'transaction_status' => $payload['transaction_status'] ?? null,
        ]);

        try {
            $midtrans->handleWebhook($payload);
        } catch (\Throwable $e) {
            Log::error('[Webhook/Midtrans] handleWebhook exception: ' . $e->getMessage());
            // Tetap balikin 200 supaya Midtrans gak retry terus-terusan.
        }

        return response('OK', 200);
    }

    // ─── Xendit Webhook (LEGACY — dibiarkan untuk jaga-jaga rollback) ─────

    /**
     * POST /api/webhooks/xendit
     *
     * Public endpoint — Xendit calls this after a payment event.
     * Secured by verifying the X-CALLBACK-TOKEN (Xendit-Webhook-Token) header.
     *
     * Xendit sends JSON with at least: id, status, external_id, amount
     */
    public function handleXenditWebhook(Request $request): \Illuminate\Http\Response
    {
        $token = $request->header('x-callback-token')
            ?? $request->header('xendit-webhook-token')
            ?? '';

        $xendit = app(\App\Services\XenditService::class);

        if (!$xendit->verifyWebhookToken($token)) {
            Log::warning('[Webhook/Xendit] Invalid webhook token');
            return response('Unauthorized', 403);
        }

        $payload = $request->json()->all();

        Log::info('[Webhook/Xendit] Received payload', [
            'invoice_id' => $payload['id'] ?? null,
            'status'     => $payload['status'] ?? null,
        ]);

        try {
            $xendit->handleWebhook($payload);
        } catch (\Throwable $e) {
            Log::error('[Webhook/Xendit] handleWebhook exception: ' . $e->getMessage());
            // Still return 200 so Xendit doesn't keep retrying
        }

        return response('OK', 200);
    }
}
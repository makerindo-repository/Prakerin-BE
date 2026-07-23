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

    // ─── Xendit Webhook ────────────────────────────────────────────────────

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

        $xendit = app(XenditService::class);

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

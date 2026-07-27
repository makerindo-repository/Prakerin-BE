<?php

namespace App\Services;

use App\Models\InboxItem;
use App\Models\NotificationLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private ?string $provider;
    private ?string $apiKey;
    private ?string $senderNumber;
    private ?string $metaPhoneNumberId;
    private ?string $qontakChannelId;
    private ?string $qontakTemplateId;
    private bool $isActive;

    public function __construct()
    {
        $this->provider          = (string) Setting::getVal('whatsapp_api_provider', 'disabled');
        $this->apiKey            = (string) Setting::getVal('whatsapp_api_key');
        $this->senderNumber      = (string) Setting::getVal('whatsapp_sender_number');
        $this->metaPhoneNumberId = (string) (Setting::getVal('whatsapp_meta_phone_number_id') ?: $this->senderNumber);
        $this->qontakChannelId   = (string) (Setting::getVal('whatsapp_qontak_channel_id') ?: $this->senderNumber);
        $this->qontakTemplateId  = (string) Setting::getVal('whatsapp_qontak_template_id');
        $this->isActive          = (bool) Setting::getVal('whatsapp_notifications_active', false);
    }

    /**
     * Check if WhatsApp is properly configured and active.
     */
    public function isConfigured(): bool
    {
        if (!$this->isActive || $this->provider === 'disabled') {
            return false;
        }

        if ($this->provider === 'mock') {
            return true;
        }

        if (empty($this->apiKey)) {
            return false;
        }

        if ($this->provider === 'twilio' && empty($this->senderNumber)) {
            return false;
        }

        if ($this->provider === 'meta' && empty($this->metaPhoneNumberId)) {
            return false;
        }

        if ($this->provider === 'qontak' && empty($this->qontakChannelId)) {
            return false;
        }

        return true;
    }

    /**
     * Send a WhatsApp message to a phone number.
     * Returns provider's message ID on success.
     *
     * @throws \Exception on failure
     */
    public function sendMessage(string $toNumber, string $message): string
    {
        if (!$this->isConfigured()) {
            throw new \Exception('WhatsApp is not configured or not active.');
        }

        $toNumber = $this->normalizeNumber($toNumber);

        return match ($this->provider) {
            'mock'   => $this->sendViaMock($toNumber, $message),
            'twilio' => $this->sendViaTwilio($toNumber, $message),
            'meta'   => $this->sendViaMeta($toNumber, $message),
            'qontak' => $this->sendViaQontak($toNumber, $message),
            default  => throw new \Exception("Unsupported WhatsApp provider: {$this->provider}"),
        };
    }

    /**
     * Test connection to the configured provider without sending a real message.
     */
    public function testConnection(): array
    {
        if ($this->provider === 'mock') {
            return $this->testMock();
        }

        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API Key / Access Token tidak dikonfigurasi.'];
        }

        try {
            return match ($this->provider) {
                'mock'     => $this->testMock(),
                'twilio'   => $this->testTwilio(),
                'meta'     => $this->testMeta(),
                'qontak'   => $this->testQontak(),
                'disabled' => ['success' => false, 'message' => 'Provider WhatsApp dinonaktifkan.'],
                default    => ['success' => false, 'message' => "Provider tidak dikenal: {$this->provider}"],
            };
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Local Mock Gateway (Development Testing) ───────────────────────────

    private function sendViaMock(string $toNumber, string $message): string
    {
        $mockId = 'mock_wa_' . uniqid();

        Log::info("==================================================");
        Log::info("[MOCK WHATSAPP GATEWAY] Message Simulated");
        Log::info("To: {$toNumber}");
        Log::info("Message ID: {$mockId}");
        Log::info("Content:\n{$message}");
        Log::info("==================================================");

        return $mockId;
    }

    private function testMock(): array
    {
        return [
            'success' => true,
            'message' => 'Berhasil! Local Mock WhatsApp Gateway aktif. Pengiriman pesan akan disimulasikan & dicatat di laravel.log.',
        ];
    }

    // ─── Meta WhatsApp Cloud API (Developer Sandbox) ─────────────────────────

    private function sendViaMeta(string $toNumber, string $message): string
    {
        $phoneNumberId = $this->metaPhoneNumberId;
        $accessToken   = $this->apiKey;
        $templateName  = Setting::getVal('whatsapp_meta_template_name', 'hello_world');
        $forceTemplate = filter_var(Setting::getVal('whatsapp_meta_force_template', false), FILTER_VALIDATE_BOOLEAN);

        $url = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";

        if ($forceTemplate) {
            $tplPayload = [
                'messaging_product' => 'whatsapp',
                'to'                => $toNumber,
                'type'              => 'template',
                'template'          => [
                    'name'     => $templateName,
                    'language' => ['code' => 'en_US'],
                ],
            ];

            $response = Http::withToken($accessToken)->post($url, $tplPayload);
            if ($response->successful()) {
                return $response->json('messages.0.id') ?? 'meta_template_ok';
            }

            $error = $response->json('error.message') ?? $response->body();
            throw new \Exception("Meta Template error: {$error}");
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toNumber,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $message,
            ],
        ];

        $response = Http::withToken($accessToken)->post($url, $payload);

        if ($response->failed()) {
            $errorData = $response->json('error') ?? [];
            $errorMessage = $errorData['message'] ?? $response->body();

            // Sandbox fallback: send official template 'hello_world'
            $tplPayload = [
                'messaging_product' => 'whatsapp',
                'to'                => $toNumber,
                'type'              => 'template',
                'template'          => [
                    'name'     => 'hello_world',
                    'language' => ['code' => 'en_US'],
                ],
            ];

            $tplResponse = Http::withToken($accessToken)->post($url, $tplPayload);
            if ($tplResponse->successful()) {
                return $tplResponse->json('messages.0.id') ?? 'meta_sandbox_ok';
            }

            throw new \Exception("Meta Cloud API error: {$errorMessage}");
        }

        return $response->json('messages.0.id') ?? 'unknown';
    }

    private function testMeta(): array
    {
        $phoneNumberId = $this->metaPhoneNumberId;
        $accessToken   = $this->apiKey;

        if (empty($phoneNumberId)) {
            return ['success' => false, 'message' => 'Meta Phone Number ID belum dikonfigurasi.'];
        }

        $url = "https://graph.facebook.com/v20.0/{$phoneNumberId}";

        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            $phone = $response->json('display_phone_number') ?? $response->json('verified_name') ?? $phoneNumberId;
            return [
                'success' => true,
                'message' => "Berhasil terhubung ke Meta Cloud API! Phone ID: {$phoneNumberId} ({$phone})",
            ];
        }

        $errorMsg = $response->json('error.message') ?? $response->body();
        return [
            'success' => false,
            'message' => "Meta Cloud API Gagal: {$errorMsg}",
        ];
    }

    // ─── Mekari Qontak WhatsApp API ──────────────────────────────────────────

    private function sendViaQontak(string $toNumber, string $message): string
    {
        $accessToken = $this->apiKey;
        $channelId   = $this->qontakChannelId;
        $templateId  = $this->qontakTemplateId;

        $url = "https://service-chat.qontak.com/api/open/v1/broadcasts/whatsapp/direct";

        $payload = [
            'to_number'              => $toNumber,
            'to_name'                => 'Pengguna Prakerin',
            'channel_integration_id' => $channelId,
        ];

        if (!empty($templateId)) {
            $payload['message_template_id'] = $templateId;
            $payload['parameters'] = [
                'body' => [
                    ['key' => '1', 'value' => 'message', 'value_text' => $message],
                ],
            ];
        } else {
            $payload['message'] = $message;
        }

        $response = Http::withToken($accessToken)->post($url, $payload);

        if ($response->failed()) {
            $error = $response->json('message') ?? $response->json('error') ?? $response->body();
            throw new \Exception("Qontak API error: {$error}");
        }

        return $response->json('data.id') ?? $response->json('data.broadcast_id') ?? 'qontak_ok';
    }

    private function testQontak(): array
    {
        $accessToken = $this->apiKey;

        $url = "https://service-chat.qontak.com/api/open/v1/channel_integrations";

        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Berhasil terhubung ke Mekari Qontak API Sandbox/Official!',
            ];
        }

        $error = $response->json('message') ?? $response->body();
        return [
            'success' => false,
            'message' => "Qontak API Gagal: {$error}",
        ];
    }

    // ─── Twilio WhatsApp API ──────────────────────────────────────────────────

    private function sendViaTwilio(string $toNumber, string $message): string
    {
        $cleanKey   = trim($this->apiKey, ": \t\n\r\0\x0B");
        $parts      = explode(':', $cleanKey, 2);
        $accountSid = trim($parts[0] ?? '');
        $authToken  = trim($parts[1] ?? '');

        if (empty($accountSid) || empty($authToken)) {
            throw new \Exception('Twilio API Key tidak valid. Format harus AccountSID:AuthToken.');
        }

        $sender = trim($this->senderNumber ?: '+14155238886');
        if (!str_starts_with($sender, 'whatsapp:')) {
            $sender = 'whatsapp:' . $sender;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post($url, [
                'From' => $sender,
                'To'   => 'whatsapp:+' . ltrim($toNumber, '+'),
                'Body' => $message,
            ]);

        if ($response->failed()) {
            $error = $response->json('message') ?? $response->body();
            throw new \Exception("Twilio API error: {$error}");
        }

        return $response->json('sid') ?? 'twilio_ok';
    }

    private function testTwilio(): array
    {
        $cleanKey   = trim($this->apiKey, ": \t\n\r\0\x0B");
        $parts      = explode(':', $cleanKey, 2);
        $accountSid = trim($parts[0] ?? '');
        $authToken  = trim($parts[1] ?? '');

        if (empty($accountSid) || empty($authToken)) {
            return ['success' => false, 'message' => 'Format Twilio API Key harus AccountSID:AuthToken.'];
        }

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");

        if ($response->successful()) {
            $friendlyName = $response->json('friendly_name') ?? $accountSid;
            return [
                'success' => true,
                'message' => "Berhasil terhubung ke Twilio! Akun: {$friendlyName}",
            ];
        }

        $err = $response->json('message') ?? $response->body();
        return [
            'success' => false,
            'message' => "Twilio: {$err}",
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Normalize and validate phone number to E.164 without leading +
     * Accepts: "08123456789", "628123456789", "+628123456789"
     *
     * @throws \InvalidArgumentException if invalid format or length
     */
    public function normalizeNumber(string $number): string
    {
        $cleanNumber = preg_replace('/\D/', '', $number);

        // Indonesian local format: 08xxx → 628xxx or 8xxx → 628xxx
        if (str_starts_with($cleanNumber, '08')) {
            $cleanNumber = '62' . substr($cleanNumber, 1);
        } elseif (str_starts_with($cleanNumber, '8')) {
            $cleanNumber = '62' . $cleanNumber;
        }

        // Validate length and format (Indonesian 628xxx format: 10-15 digits total)
        if (!preg_match('/^628[0-9]{8,12}$/', $cleanNumber)) {
            throw new \InvalidArgumentException(
                "Nomor WhatsApp '{$number}' tidak valid. Gunakan format Indonesia yang valid (contoh: 08123456789 atau 628123456789)."
            );
        }

        return $cleanNumber;
    }
}

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
    private bool $isActive;

    public function __construct()
    {
        $this->provider     = Setting::getVal('whatsapp_api_provider', 'disabled');
        $this->apiKey       = Setting::getVal('whatsapp_api_key');
        $this->senderNumber = Setting::getVal('whatsapp_sender_number');
        $this->isActive     = (bool) Setting::getVal('whatsapp_notifications_active', false);
    }

    /**
     * Check if WhatsApp is properly configured and active.
     */
    public function isConfigured(): bool
    {
        return $this->isActive
            && !empty($this->apiKey)
            && !empty($this->senderNumber)
            && $this->provider !== 'disabled';
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
            'twilio' => $this->sendViaTwilio($toNumber, $message),
            default  => throw new \Exception("Unsupported WhatsApp provider: {$this->provider}"),
        };
    }

    /**
     * Test connection to the configured provider without sending a real message.
     */
    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API Key tidak dikonfigurasi.'];
        }

        if (empty($this->senderNumber)) {
            return ['success' => false, 'message' => 'Nomor pengirim tidak dikonfigurasi.'];
        }

        try {
            return match ($this->provider) {
                'twilio'   => $this->testTwilio(),
                'disabled' => ['success' => false, 'message' => 'Provider WhatsApp dinonaktifkan.'],
                default    => ['success' => false, 'message' => "Provider tidak dikenal: {$this->provider}"],
            };
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Twilio ───────────────────────────────────────────────────────────────

    private function sendViaTwilio(string $toNumber, string $message): string
    {
        // Twilio API key format: "AccountSID:AuthToken"
        [$accountSid, $authToken] = explode(':', $this->apiKey, 2);

        $fromNumber = 'whatsapp:+' . ltrim($this->senderNumber, '+');
        $toFormatted = 'whatsapp:+' . ltrim($toNumber, '+');

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => $fromNumber,
                'To'   => $toFormatted,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            $error = $response->json('message') ?? $response->body();
            throw new \Exception("Twilio error: {$error}");
        }

        return $response->json('sid') ?? 'unknown';
    }

    private function testTwilio(): array
    {
        [$accountSid, $authToken] = explode(':', $this->apiKey . ':', 2);

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");

        if ($response->successful()) {
            $friendlyName = $response->json('friendly_name') ?? $accountSid;
            return [
                'success' => true,
                'message' => "Berhasil terhubung ke Twilio! Akun: {$friendlyName}",
            ];
        }

        return [
            'success' => false,
            'message' => 'Twilio: ' . ($response->json('message') ?? 'Autentikasi gagal.'),
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

        // Indonesian local format: 08xxx → 628xxx
        if (str_starts_with($cleanNumber, '08')) {
            $cleanNumber = '62' . substr($cleanNumber, 1);
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

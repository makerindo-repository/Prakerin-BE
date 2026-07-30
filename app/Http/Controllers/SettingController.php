<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SettingController extends Controller
{
    /**
     * Get all settings in key-value format.
     */
    public function index()
    {
        $settings = Setting::all()->mapWithKeys(function ($item) {
            return [$item->key => $item->getVal($item->key)];
        });

        return response()->json([
            'message' => 'Settings retrieved successfully',
            'data' => $settings
        ]);
    }

    /**
     * Batch update system settings.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($data['settings'] as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                // Cast input values based on the setting type
                if ($setting->type === 'boolean') {
                    $valStr = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                } elseif ($setting->type === 'json') {
                    $valStr = is_array($value) ? json_encode($value) : $value;
                } else {
                    $valStr = (string)$value;
                }
                $setting->update(['value' => $valStr]);
            } else {
                // Self-healing: create the setting if it doesn't exist (e.g. if DB seeder wasn't run on VPS)
                $type = 'string';
                if (is_bool($value)) {
                    $type = 'boolean';
                    $valStr = $value ? 'true' : 'false';
                } elseif (is_array($value)) {
                    $type = 'json';
                    $valStr = json_encode($value);
                } elseif (is_numeric($value)) {
                    $type = 'number';
                    $valStr = (string)$value;
                } else {
                    $valStr = (string)$value;
                }

                Setting::create([
                    'key' => $key,
                    'value' => $valStr,
                    'type' => $type,
                ]);
            }
        }

        // Programmatically clear config cache so dynamic overrides apply cleanly
        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
        } catch (\Exception $e) {
            Log::warning('Artisan config:clear failed: ' . $e->getMessage());
        }

        // Return the fresh casted list of settings
        $settings = Setting::all()->mapWithKeys(function ($item) {
            return [$item->key => $item->getVal($item->key)];
        });

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => $settings
        ]);
    }

    /**
     * Subset pengaturan yang aman dibaca semua role yang login (bukan cuma
     * admin) — dipakai misalnya buat sidebar butuh link "Kelas Pra-Magang".
     * SENGAJA whitelist manual di sini, jangan pernah expose semua Setting::all()
     * ke endpoint ini karena ada value sensitif (SMTP password, AI API key, dst).
     */
    public function publicSettings()
    {
        $whitelist = ['pre_internship_class_url', 'platform_name', 'support_email', 'support_phone', 'pro_monthly_price', 'pro_yearly_price'];

        $settings = Setting::whereIn('key', $whitelist)
            ->get()
            ->mapWithKeys(fn ($item) => [$item->key => $item->getVal($item->key)]);

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Test the connection to the SMTP server.
     */
    public function testSmtp()
    {
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');

        if (empty($host)) {
            return response()->json([
                'status' => 'error',
                'message' => 'SMTP Host is not configured.'
            ], 400);
        }

        // 5 second timeout socket check
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        if (is_resource($connection)) {
            fclose($connection);
            return response()->json([
                'status' => 'success',
                'message' => "Successfully connected to SMTP server at {$host}:{$port}!"
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => "Failed to reach SMTP server at {$host}:{$port}. Error: {$errstr} ({$errno})"
        ], 400);
    }

    /**
     * Test the connection to the Gemini AI API.
     */
    public function testAiKey()
    {
        $apiKey = config('gemini.api_key');
        if (empty($apiKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gemini API Key is empty.'
            ], 400);
        }

        try {
            // Using a cheap lightweight model for API testing
            $result = \Gemini\Laravel\Facades\Gemini::generativeModel("gemini-3.1-flash-lite")
                ->generateContent("Hello, verify API connection. Keep response under 3 words.");

            if ($result && !empty($result->text())) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Successfully connected to Google Gemini API! Response: ' . trim($result->text())
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Received an empty response from Gemini API.'
            ], 400);
        } catch (\Exception $e) {
            Log::error('SMTP/Settings AI Connection Test Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to connect to Gemini API: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Test the WhatsApp (Twilio) connection.
     * POST /api/v1/settings/test-whatsapp
     */
    public function testWhatsApp()
    {
        $whatsApp = new \App\Services\WhatsAppService();
        $result   = $whatsApp->testConnection();

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json([
            'status'  => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ], $statusCode);
    }

    /**
     * Broadcast custom WhatsApp notification to targeted audience groups.
     * POST /api/v1/settings/broadcast-whatsapp
     */
    public function sendWhatsAppBroadcast(Request $request)
    {
        $validated = $request->validate([
            'target_group'           => 'required|string|in:all_wa_users,unapplied_students,active_interns,pro_users,test_single_user',
            'title'                  => 'required|string|max:150',
            'message'                => 'required|string',
            'action_url'             => 'nullable|string',
            'single_user_identifier' => 'required_if:target_group,test_single_user|nullable|string',
        ]);

        $whatsAppService = new \App\Services\WhatsAppService();
        if (!$whatsAppService->isConfigured()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'WhatsApp Gateway belum dikonfigurasi atau belum diaktifkan oleh admin.',
            ], 400);
        }

        $targetGroup = $validated['target_group'];
        $title       = $validated['title'];
        $rawMessage  = $validated['message'];
        $actionUrl   = $validated['action_url'] ?? config('app.frontend_url', 'http://localhost:3000') . '/dashboard';

        $query = \App\Models\User::query();

        if ($targetGroup === 'test_single_user') {
            $identifier = $validated['single_user_identifier'];
            $query->where(function ($q) use ($identifier) {
                $q->where('id', $identifier)
                  ->orWhere('email', $identifier)
                  ->orWhere('whatsapp_number', $identifier);
            });
        } elseif ($targetGroup === 'unapplied_students') {
            $query->where('role', 'student')
                  ->where('whatsapp_notifications_enabled', true)
                  ->whereNotNull('whatsapp_number')
                  ->whereDoesntHave('student.curriculumVitae.internshipApplications');
        } elseif ($targetGroup === 'active_interns') {
            $query->where('role', 'student')
                  ->where('whatsapp_notifications_enabled', true)
                  ->whereNotNull('whatsapp_number')
                  ->whereHas('student.curriculumVitae.internshipApplications', function ($q) {
                      $q->where('status', 'approved');
                  });
        } elseif ($targetGroup === 'pro_users') {
            $query->where('is_pro', true)
                  ->where('whatsapp_notifications_enabled', true)
                  ->whereNotNull('whatsapp_number');
        } elseif ($targetGroup === 'all_wa_users') {
            $query->where('whatsapp_notifications_enabled', true)
                  ->whereNotNull('whatsapp_number');
        }

        $targetUsers = $query->get();

        if ($targetUsers->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada pengguna yang memenuhi kriteria penerima WhatsApp (pastikan nomor WA valid dan notifikasi aktif).',
            ], 404);
        }

        $notificationService = app(\App\Services\NotificationService::class);
        $queuedCount = 0;

        foreach ($targetUsers as $targetUser) {
            // Replace dynamic placeholders
            $personalizedMessage = str_replace(
                ['{name}', '{role}', '{link}'],
                [$targetUser->username ?? 'Pengguna', ucfirst($targetUser->role ?? 'User'), $actionUrl],
                $rawMessage
            );

            $notificationService->notify(
                userId: $targetUser->id,
                title: $title,
                content: $personalizedMessage,
                type: 'broadcast',
                actionUrl: $actionUrl
            );

            $queuedCount++;
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Berhasil menjadwalkan broadcast WhatsApp untuk {$queuedCount} pengguna!",
            'data'    => [
                'target_group' => $targetGroup,
                'queued_count' => $queuedCount,
            ],
        ]);
    }

    /**
     * Send an email broadcast notification to targeted user segments.
     * POST /api/v1/settings/broadcast-email
     */
    public function sendEmailBroadcast(Request $request)
    {
        $validated = $request->validate([
            'target_group'           => 'required|string|in:all_email_users,unapplied_students,active_interns,pro_users,test_single_user',
            'title'                  => 'required|string|max:150',
            'message'                => 'required|string',
            'action_url'             => 'nullable|string',
            'single_user_identifier' => 'required_if:target_group,test_single_user|nullable|string',
            'header_logo_url'        => 'nullable|string',
            'header_title'           => 'nullable|string',
            'header_icon'            => 'nullable|string',
        ]);

        // If custom header logo URL is provided, save to app_logo setting
        if (!empty($validated['header_logo_url'])) {
            \App\Models\Setting::updateOrCreate(
                ['key' => 'app_logo'],
                ['value' => $validated['header_logo_url'], 'type' => 'string']
            );
        }

        $targetGroup = $validated['target_group'];
        $title       = $validated['title'];
        $rawMessage  = $validated['message'];
        $actionUrl   = $validated['action_url'] ?? config('app.frontend_url', 'http://localhost:3000') . '/dashboard';

        $query = \App\Models\User::query();

        if ($targetGroup === 'test_single_user') {
            $identifier = $validated['single_user_identifier'];
            $query->where(function ($q) use ($identifier) {
                $q->where('id', $identifier)
                  ->orWhere('email', $identifier)
                  ->orWhere('whatsapp_number', $identifier);
            });
        } elseif ($targetGroup === 'unapplied_students') {
            $query->where('role', 'student')
                  ->where('email_notifications_enabled', true)
                  ->whereNotNull('email')
                  ->whereDoesntHave('student.curriculumVitae.internshipApplications');
        } elseif ($targetGroup === 'active_interns') {
            $query->where('role', 'student')
                  ->where('email_notifications_enabled', true)
                  ->whereNotNull('email')
                  ->whereHas('student.curriculumVitae.internshipApplications', function ($q) {
                      $q->where('status', 'approved');
                  });
        } elseif ($targetGroup === 'pro_users') {
            $query->where('is_pro', true)
                  ->where('email_notifications_enabled', true)
                  ->whereNotNull('email');
        } elseif ($targetGroup === 'all_email_users') {
            $query->where('email_notifications_enabled', true)
                  ->whereNotNull('email');
        }

        $targetUsers = $query->get();

        if ($targetUsers->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada pengguna yang memenuhi kriteria penerima Email (pastikan email valid dan notifikasi email aktif).',
            ], 404);
        }

        $notificationService = app(\App\Services\NotificationService::class);
        $queuedCount = 0;

        foreach ($targetUsers as $targetUser) {
            $personalizedMessage = str_replace(
                ['{name}', '{role}', '{link}'],
                [$targetUser->username ?? 'Pengguna', ucfirst($targetUser->role ?? 'User'), $actionUrl],
                $rawMessage
            );

            $notificationService->notify(
                userId: $targetUser->id,
                title: $title,
                content: $personalizedMessage,
                type: 'broadcast',
                actionUrl: $actionUrl
            );

            $queuedCount++;
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Berhasil menjadwalkan broadcast Email untuk {$queuedCount} pengguna!",
            'data'    => [
                'target_group' => $targetGroup,
                'queued_count' => $queuedCount,
            ],
        ]);
    }

    /**
     * Test the connection to the Midtrans API (Payment Gateway).
     *
     * Midtrans Core API gak punya endpoint "balance"/ping yang ringan kayak
     * Xendit, jadi triknya: panggil endpoint status transaksi dengan
     * order_id yang SENGAJA gak ada. Kalau Server Key valid, Midtrans balas
     * 404 "Transaction doesn't exist" (artinya auth-nya lolos, cuma
     * datanya emang gak ada — itu tandanya key BENAR). Kalau key salah,
     * Midtrans balas 401 Unauthorized. Gak ada transaksi/invoice apapun
     * yang dibuat, aman dipanggil berkali-kali.
     *
     * POST /api/v1/settings/test-midtrans
     */
    public function testMidtrans()
    {
        $serverKey = config('subscription.midtrans.server_key');
        $baseUrl   = config('subscription.midtrans.base_url');
        $isProd    = config('subscription.midtrans.is_production');

        if (empty($serverKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Midtrans Server Key masih kosong.',
            ], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($serverKey, '')
                ->timeout(15)
                ->get("{$baseUrl}/v2/CONNECTION-TEST-ORDER-ID-INI-SENGAJA-GA-ADA/status");

            // 404 = auth lolos, cuma order_id-nya emang gak ada — ini yang
            // kita HARAPKAN, artinya Server Key valid.
            if ($response->status() === 404) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Berhasil terhubung ke Midtrans API! Mode: ' . ($isProd ? 'Live/Production' : 'Sandbox'),
                ]);
            }

            if ($response->status() === 401) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Server Key ditolak Midtrans (401 Unauthorized). Cek ulang key & mode sandbox/production-nya.',
                ], 400);
            }

            $body = $response->json();

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal terhubung ke Midtrans: ' . ($body['status_message'] ?? $response->body()),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Settings Midtrans Connection Test Error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghubungi Midtrans API: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Test the connection to the Xendit API (Payment Gateway). LEGACY —
     * dibiarkan buat jaga-jaga rollback, gak dipakai lagi sejak migrasi ke
     * Midtrans.
     * Pakai endpoint /balance — ringan, cuma butuh secret key valid, tidak
     * membuat invoice/transaksi apapun jadi aman dipanggil berkali-kali.
     * POST /api/v1/settings/test-xendit
     */
    public function testXendit()
    {
        $secretKey = config('subscription.xendit.secret_key');

        if (empty($secretKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Xendit Secret Key masih kosong.',
            ], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($secretKey, '')
                ->timeout(15)
                ->get('https://api.xendit.co/balance');

            if ($response->successful()) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Berhasil terhubung ke Xendit API! Mode: ' .
                        (str_starts_with($secretKey, 'xnd_development_') ? 'Test/Development' : 'Live/Production'),
                ]);
            }

            $body = $response->json();

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal terhubung ke Xendit: ' . ($body['message'] ?? $response->body()),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Settings Xendit Connection Test Error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghubungi Xendit API: ' . $e->getMessage(),
            ], 400);
        }
    }
}
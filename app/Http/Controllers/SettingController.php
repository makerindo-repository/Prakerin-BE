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
        $whitelist = ['pre_internship_class_url', 'platform_name', 'support_email', 'support_phone'];

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
}
<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'platform_name',
                'value' => 'Prakerin Management Portal',
                'type' => 'string',
            ],
            [
                'key' => 'support_email',
                'value' => 'support@prakerin.com',
                'type' => 'string',
            ],
            [
                'key' => 'support_phone',
                'value' => '+62 812-3456-7890',
                'type' => 'string',
            ],
            [
                'key' => 'support_address',
                'value' => 'Bandung, West Java, Indonesia',
                'type' => 'string',
            ],

            // Internship Policies
            [
                'key' => 'max_concurrent_applications',
                'value' => '3',
                'type' => 'number',
            ],
            [
                'key' => 'auto_approve_schools',
                'value' => 'false',
                'type' => 'boolean',
            ],
            [
                'key' => 'auto_approve_companies',
                'value' => 'false',
                'type' => 'boolean',
            ],
            [
                'key' => 'auto_approve_students',
                'value' => 'false',
                'type' => 'boolean',
            ],
            [
                'key' => 'mou_number_prefix',
                'value' => 'MOU/{YEAR}/{MONTH}/{ID}',
                'type' => 'string',
            ],
            [
                'key' => 'cert_number_prefix',
                'value' => 'CERT/{YEAR}/{ID}',
                'type' => 'string',
            ],
            [
                'key' => 'min_internship_duration',
                'value' => '1',
                'type' => 'number',
            ],
            [
                'key' => 'max_internship_duration',
                'value' => '52',
                'type' => 'number',
            ],
            [
                'key' => 'pre_internship_class_url',
                'value' => 'https://makerindo.myr.id/',
                'type' => 'string',
            ],

            // Integrations
            [
                'key' => 'ai_provider',
                'value' => 'gemini',
                'type' => 'string',
            ],
            [
                'key' => 'ai_api_key',
                'value' => env('GEMINI_API_KEY', ''),
                'type' => 'string',
            ],
            [
                'key' => 'midtrans_server_key',
                'value' => env('MIDTRANS_SERVER_KEY', ''),
                'type' => 'string',
            ],
            [
                'key' => 'midtrans_client_key',
                'value' => env('MIDTRANS_CLIENT_KEY', ''),
                'type' => 'string',
            ],
            [
                'key' => 'midtrans_is_production',
                'value' => env('MIDTRANS_IS_PRODUCTION', 'false'),
                'type' => 'boolean',
            ],
            // Xendit — LEGACY, tidak dipakai lagi sejak migrasi ke Midtrans.
            // Dibiarkan (bukan dihapus) buat jaga-jaga rollback cepat.
            [
                'key' => 'xendit_secret_key',
                'value' => env('XENDIT_SECRET_KEY', ''),
                'type' => 'string',
            ],
            [
                'key' => 'xendit_webhook_token',
                'value' => env('XENDIT_WEBHOOK_TOKEN', ''),
                'type' => 'string',
            ],
            [
                'key' => 'xendit_payment_methods',
                'value' => env('XENDIT_PAYMENT_METHODS', ''),
                'type' => 'string',
            ],
            [
                'key' => 'recaptcha_enabled',
                'value' => 'false',
                'type' => 'boolean',
            ],
            [
                'key' => 'recaptcha_site_key',
                'value' => '',
                'type' => 'string',
            ],
            [
                'key' => 'recaptcha_secret_key',
                'value' => '',
                'type' => 'string',
            ],

            // SMTP Settings
            [
                'key' => 'smtp_host',
                'value' => 'smtp.mailtrap.io',
                'type' => 'string',
            ],
            [
                'key' => 'smtp_port',
                'value' => '2525',
                'type' => 'number',
            ],
            [
                'key' => 'smtp_username',
                'value' => '',
                'type' => 'string',
            ],
            [
                'key' => 'smtp_password',
                'value' => '',
                'type' => 'string',
            ],
            [
                'key' => 'smtp_encryption',
                'value' => 'tls',
                'type' => 'string',
            ],
            [
                'key' => 'smtp_from_email',
                'value' => 'noreply@prakerin.com',
                'type' => 'string',
            ],
            // Pro Tier Access Settings
            [
                'key' => 'pro_access_ai_cv_generator',
                'value' => 'true',
                'type' => 'boolean',
            ],
            [
                'key' => 'pro_access_ai_analytics',
                'value' => 'true',
                'type' => 'boolean',
            ],
            [
                'key' => 'pro_access_ai_report',
                'value' => 'true',
                'type' => 'boolean',
            ],

            // Subscription Pricing & Company Bank Settings
            [
                'key' => 'pro_monthly_price',
                'value' => '99000',
                'type' => 'number',
            ],
            [
                'key' => 'pro_yearly_price',
                'value' => '999000',
                'type' => 'number',
            ],
            [
                'key' => 'company_bank_name',
                'value' => 'Bank Central Asia (BCA)',
                'type' => 'string',
            ],
            [
                'key' => 'company_bank_account_number',
                'value' => '',
                'type' => 'string',
            ],
            [
                'key' => 'company_bank_account_name',
                'value' => '',
                'type' => 'string',
            ],
            [
                'key' => 'company_bank_address',
                'value' => '',
                'type' => 'string',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                ]
            );
        }
    }
}
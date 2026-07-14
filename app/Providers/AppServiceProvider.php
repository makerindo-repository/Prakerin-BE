<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            try {
                // Fetch settings values indexed by key
                $settings = \App\Models\Setting::all()->pluck('value', 'key');
                
                // Dynamic mail config overrides
                if (isset($settings['smtp_host']) && !empty($settings['smtp_host'])) {
                    config([
                        'mail.mailers.smtp.host' => $settings['smtp_host'],
                        'mail.mailers.smtp.port' => (int) ($settings['smtp_port'] ?? 587),
                        'mail.mailers.smtp.username' => $settings['smtp_username'] ?? '',
                        'mail.mailers.smtp.password' => $settings['smtp_password'] ?? '',
                        'mail.mailers.smtp.encryption' => $settings['smtp_encryption'] ?? 'tls',
                        'mail.from.address' => $settings['smtp_from_email'] ?? config('mail.from.address'),
                        'mail.from.name' => $settings['smtp_from_name'] ?? config('mail.from.name'),
                    ]);
                }

                // Dynamic reCAPTCHA config overrides
                if (isset($settings['recaptcha_site_key']) && !empty($settings['recaptcha_site_key'])) {
                    config([
                        'services.recaptcha.site' => $settings['recaptcha_site_key'],
                        'services.recaptcha.secret' => $settings['recaptcha_secret_key'],
                    ]);
                }

                // Dynamic AI / Gemini config overrides
                if (isset($settings['ai_api_key']) && !empty($settings['ai_api_key'])) {
                    config([
                        'gemini.api_key' => $settings['ai_api_key'],
                    ]);
                }
            } catch (\Exception $e) {
                // Avoid breaking artisan commands if database is not fully set up
            }
        }
    }
}

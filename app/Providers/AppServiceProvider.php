<?php

namespace App\Providers;

use App\Models\InboxItem;
use App\Observers\InboxItemObserver;
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
        // Register model observers
        InboxItem::observe(InboxItemObserver::class);

        try {
            // Fetch settings values indexed by key
            $settings = \App\Models\Setting::all()->pluck('value', 'key');
            
            // Dynamic mail config overrides
            if (isset($settings['smtp_host']) && !empty($settings['smtp_host'])) {
                config([
                    'mail.mailers.smtp.host'       => $settings['smtp_host'],
                    'mail.mailers.smtp.port'       => (int) ($settings['smtp_port'] ?? 587),
                    'mail.mailers.smtp.username'   => $settings['smtp_username'] ?? '',
                    'mail.mailers.smtp.password'   => $settings['smtp_password'] ?? '',
                    'mail.mailers.smtp.encryption' => $settings['smtp_encryption'] ?? 'tls',
                    'mail.from.address'            => $settings['smtp_from_email'] ?? config('mail.from.address'),
                    'mail.from.name'               => $settings['smtp_from_name'] ?? config('mail.from.name'),
                ]);
            }

            // Dynamic reCAPTCHA config overrides
            if (isset($settings['recaptcha_site_key']) && !empty($settings['recaptcha_site_key'])) {
                config([
                    'services.recaptcha.site'   => $settings['recaptcha_site_key'],
                    'services.recaptcha.secret' => $settings['recaptcha_secret_key'],
                ]);
            }

            // Dynamic AI / Gemini config overrides
            if (isset($settings['ai_api_key']) && !empty($settings['ai_api_key'])) {
                config([
                    'gemini.api_key' => $settings['ai_api_key'],
                ]);
            }

            // Dynamic Xendit (Payment Gateway) config overrides
            if (isset($settings['xendit_secret_key']) && !empty($settings['xendit_secret_key'])) {
                config([
                    'subscription.xendit.secret_key' => $settings['xendit_secret_key'],
                ]);
            }
            if (isset($settings['xendit_webhook_token']) && !empty($settings['xendit_webhook_token'])) {
                config([
                    'subscription.xendit.webhook_token' => $settings['xendit_webhook_token'],
                ]);
            }
            if (isset($settings['xendit_payment_methods']) && !empty($settings['xendit_payment_methods'])) {
                config([
                    'subscription.xendit.payment_methods' => array_values(array_filter(
                        explode(',', (string) $settings['xendit_payment_methods'])
                    )),
                ]);
            }

        } catch (\Throwable $e) {
            // Avoid breaking artisan commands if database is not fully set up or table does not exist yet
        }
    }
}
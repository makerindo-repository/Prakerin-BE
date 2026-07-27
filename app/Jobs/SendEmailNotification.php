<?php

namespace App\Jobs;

use App\Mail\InboxNotificationMail;
use App\Models\InboxItem;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    protected InboxItem $inboxItem;
    protected User $user;
    protected int $logId;

    public function __construct(InboxItem $inboxItem, User $user, int $logId)
    {
        $this->inboxItem = $inboxItem;
        $this->user      = $user;
        $this->logId     = $logId;
    }

    public function handle(): void
    {
        try {
            // Dynamically load fresh SMTP settings from DB in worker thread
            $settings = \App\Models\Setting::all()->pluck('value', 'key');
            if (isset($settings['smtp_host']) && !empty($settings['smtp_host'])) {
                config([
                    'mail.default'                 => 'smtp',
                    'mail.mailers.smtp.host'       => $settings['smtp_host'],
                    'mail.mailers.smtp.port'       => (int) ($settings['smtp_port'] ?? 587),
                    'mail.mailers.smtp.username'   => $settings['smtp_username'] ?? '',
                    'mail.mailers.smtp.password'   => $settings['smtp_password'] ?? '',
                    'mail.mailers.smtp.encryption' => $settings['smtp_encryption'] ?? 'tls',
                    'mail.from.address'            => $settings['smtp_from_email'] ?? config('mail.from.address'),
                    'mail.from.name'               => $settings['smtp_from_name'] ?? config('mail.from.name'),
                ]);
                Mail::purge('smtp');
            }

            Mail::to($this->user->email)
                ->send(new InboxNotificationMail($this->inboxItem));

            NotificationLog::find($this->logId)?->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            Log::info("[SendEmailNotification] Email sent to user {$this->user->id} ({$this->user->email})");

        } catch (\Throwable $e) {
            Log::error("[SendEmailNotification] Failed: " . $e->getMessage());

            NotificationLog::find($this->logId)?->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw so the queue worker knows to retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[SendEmailNotification] Permanently failed for user {$this->user->id}: " . $exception->getMessage());

        NotificationLog::find($this->logId)?->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}

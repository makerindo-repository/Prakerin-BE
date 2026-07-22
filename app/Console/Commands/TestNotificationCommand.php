<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class TestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test 
                            {--user= : User ID or email to send test notification to} 
                            {--channel=all : Channel to test (email, whatsapp, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches a test notification (Inbox, Email, WhatsApp) to verify system functionality';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $userIdentifier = $this->option('user');
        $channel = $this->option('channel');

        if (!$userIdentifier) {
            $user = User::first();
        } else {
            $user = User::where('id', $userIdentifier)
                ->orWhere('email', $userIdentifier)
                ->first();
        }

        if (!$user) {
            $this->error("User not found: {$userIdentifier}");
            return 1;
        }

        $this->info("Dispatching test notification for user: {$user->username} ({$user->email})");
        $this->line("Target Channel: {$channel}");

        try {
            $inboxItem = $notificationService->notify(
                userId: $user->id,
                title: "🧪 Tes Notifikasi System",
                content: "Ini adalah pesan tes otomatis dari sistem notifikasi Prakerin. Jika kamu menerima ini, setup telah berhasil!",
                type: "system_test",
                actionUrl: config('app.frontend_url') . "/dashboard/inbox"
            );

            $this->info("✅ Inbox Item created with ID: {$inboxItem->id}");
            $this->info("Check queue jobs table or run 'php artisan queue:work' to process.");

            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Failed to send notification: " . $e->getMessage());
            return 1;
        }
    }
}

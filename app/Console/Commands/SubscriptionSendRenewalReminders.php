<?php

namespace App\Console\Commands;

use App\Models\Revenue;
use App\Models\Student;
use App\Models\Subscription;
use App\Services\NotificationService;
use App\Services\MidtransService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionSendRenewalReminders extends Command
{
    protected $signature = 'subscription:send-renewal-reminders';
    protected $description = 'Send renewal reminder notifications to students whose subscriptions are due within 3 days.';

    public function handle(MidtransService $paymentGateway, NotificationService $notifications): int
    {
        $reminderDays = config('subscription.renewal_reminder_days', 3);

        $subscriptions = Subscription::with(['student'])
            ->where('status', 'active')
            ->where('renewal_date', '<=', now()->addDays($reminderDays))
            ->where('renewal_date', '>', now()) // not yet expired
            ->get();

        $this->info("Found {$subscriptions->count()} subscription(s) due for renewal reminder.");

        $sent  = 0;
        $errors = 0;

        foreach ($subscriptions as $subscription) {
            $student = $subscription->student;

            if (!$student) {
                $this->warn("Student not found for subscription #{$subscription->id}, skipping.");
                continue;
            }

            try {
                $daysRemaining = max(1, (int) ceil(now()->diffInDays($subscription->renewal_date, false)));

                // Send in-app + email + WA notification
                if ($student->user_id) {
                    $paymentLink = config('app.frontend_url') . '/dashboard/profile';

                    $notifications->notify(
                        $student->user_id,
                        '⏰ Langganan Premium Segera Berakhir',
                        "Langganan Premium kamu akan berakhir dalam {$daysRemaining} hari. "
                        . "Perpanjang sekarang agar akses tidak terputus. "
                        . "Buka halaman Profil untuk melakukan perpanjangan.",
                        'subscription_renewal_reminder',
                        $paymentLink,
                        'Subscription',
                        $subscription->id,
                    );
                }

                $this->info("  ✓ Reminder sent for student {$student->name} (#{$student->id})");
                Log::info("[SubscriptionRenewal] Reminder sent for student {$student->id}, subscription #{$subscription->id}");
                $sent++;

            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for student #{$student->id}: " . $e->getMessage());
                Log::error("[SubscriptionRenewal] Failed for student {$student->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("Done. Sent: {$sent}, Errors: {$errors}.");
        Log::info("[SubscriptionRenewal] Completed. Sent={$sent}, Errors={$errors}");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
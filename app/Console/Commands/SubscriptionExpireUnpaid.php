<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SubscriptionExpireUnpaid extends Command
{
    protected $signature = 'subscription:expire-unpaid';
    protected $description = 'Expire overdue subscriptions and downgrade student tiers to free.';

    public function handle(NotificationService $notifications): int
    {
        $subscriptions = Subscription::with(['student'])
            ->where('status', 'active')
            ->where('renewal_date', '<', now())
            ->get();

        $this->info("Found {$subscriptions->count()} overdue subscription(s).");

        $expired = 0;
        $errors  = 0;

        foreach ($subscriptions as $subscription) {
            $student = $subscription->student;

            if (!$student) {
                $this->warn("Student not found for subscription #{$subscription->id}, expiring anyway.");
            }

            try {
                // Expire the subscription
                $subscription->update(['status' => 'expired']);

                // Downgrade student tier
                if ($student) {
                    Student::where('id', $subscription->user_id)->update([
                        'status_subscription' => 'free',
                    ]);

                    if ($student->user_id) {
                        \App\Models\User::where('id', $student->user_id)->update(['is_pro' => false]);
                    }

                    // Send expiry notification
                    if ($student->user_id) {
                        $notifications->notify(
                            $student->user_id,
                            '😔 Langganan Premium Telah Berakhir',
                            'Langganan Premium kamu sudah berakhir. Upgrade kembali untuk mengakses semua fitur premium.',
                            'subscription_expired',
                            config('app.frontend_url') . '/dashboard',
                            'Subscription',
                            $subscription->id,
                        );
                    }

                    $this->info("  ✓ Expired and downgraded student {$student->name} (#{$student->id})");
                    Log::info("[SubscriptionExpire] Expired subscription #{$subscription->id} for student {$student->id}");
                }

                $expired++;

            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for subscription #{$subscription->id}: " . $e->getMessage());
                Log::error("[SubscriptionExpire] Failed for subscription {$subscription->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("Done. Expired: {$expired}, Errors: {$errors}.");
        Log::info("[SubscriptionExpire] Completed. Expired={$expired}, Errors={$errors}");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

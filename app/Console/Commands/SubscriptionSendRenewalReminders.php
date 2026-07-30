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
                // Generate renewal invoice lewat payment gateway aktif (Midtrans)
                $referenceId = 'RENEW-' . strtoupper(Str::random(10)) . '-' . $student->id;
                $invoice = $paymentGateway->createInvoice(
                    $subscription->amount,
                    $student,
                    $referenceId,
                    'Perpanjangan Premium Subscription Prakerin'
                );

                // Create pending Revenue record for this renewal period
                $nextPeriodEnd = $subscription->renewal_date->copy()->addDays(
                    config("subscription.packages.monthly.duration_days", 30)
                );

                Revenue::create([
                    'subscription_id'      => $subscription->id,
                    'user_id'              => $student->id,
                    'user_type'            => $subscription->user_type,
                    'amount'               => $subscription->amount,
                    'currency'             => $subscription->currency,
                    'payment_status'       => 'pending',
                    'period_start'         => $subscription->renewal_date,
                    'period_end'           => $nextPeriodEnd,
                    'external_id'          => $referenceId,
                    'payment_reference_id' => $invoice['id'],
                    'invoice_url'          => $invoice['invoice_url'] ?? null,
                    'qr_code_url'          => $invoice['qr_code_url'] ?? null,
                    'expiry_date'          => $invoice['expiry_date'] ?? null,
                ]);

                // Send in-app + email + WA notification
                if ($student->user_id) {
                    // Midtrans Core API (QRIS) tidak punya halaman invoice
                    // ter-hosted kayak Xendit dulu (invoice_url selalu null),
                    // jadi arahkan ke halaman profil — pending_payment yang
                    // baru dibuat di atas otomatis kedeteksi di sana dan QR-nya
                    // langsung tampil lagi (lihat SubscriptionController::getUserSubscription
                    // & UpgradePremiumSection.tsx di frontend).
                    $paymentLink = config('app.frontend_url') . '/dashboard/profile';

                    $notifications->notify(
                        $student->user_id,
                        '⏰ Langganan Premium Segera Berakhir',
                        "Langganan Premium kamu akan berakhir dalam {$reminderDays} hari. "
                        . "Bayar sekarang agar akses tidak terputus. "
                        . "Buka halaman Profil untuk lihat QR pembayarannya.",
                        'subscription_renewal_reminder',
                        $paymentLink,
                        'Subscription',
                        $subscription->id,
                    );
                }

                $this->info("  ✓ Reminder sent for student {$student->name} (#{$student->id})");
                Log::info("[SubscriptionRenewal] Reminder sent for student {$student->id}, invoice {$invoice['id']}");
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
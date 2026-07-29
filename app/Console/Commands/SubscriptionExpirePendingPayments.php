<?php

namespace App\Console\Commands;

use App\Models\Revenue;
use App\Services\XenditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SubscriptionExpirePendingPayments extends Command
{
    protected $signature = 'subscription:expire-pending-payments';

    protected $description = 'Expire pending Xendit invoices that have passed their payment window — safety net for when the payer closes the QRIS modal (stops polling) and the Xendit webhook never arrives.';

    public function handle(XenditService $xendit): int
    {
        // Toleransi jaga-jaga selisih jam server kita vs Xendit, dan
        // supaya invoice yang baru sedikit lewat batas gak langsung
        // ke-expire-kan padahal Xendit sendiri mungkin masih memproses.
        $bufferSeconds = 30;
        $expirySeconds = config('subscription.payment_expiry_seconds', 300);
        $cutoff        = now()->subSeconds($expirySeconds + $bufferSeconds);

        $pending = Revenue::where('payment_status', 'pending')
            ->whereNotNull('xendit_invoice_id')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $this->info("Found {$pending->count()} pending payment(s) past their expiry window.");

        $expired = 0;
        $saved   = 0;

        foreach ($pending as $revenue) {
            try {
                $status = $xendit->getInvoiceStatus($revenue->xendit_invoice_id);

                if ($status['paid']) {
                    // Ternyata sudah dibayar, tapi webhook & polling frontend
                    // sama-sama gak sempat nyampe (mis. siswa bayar lewat
                    // link invoice SETELAH nutup modal). Selamatkan — jangan
                    // sampai malah di-expire-kan padahal sudah lunas.
                    $xendit->confirmPayment($revenue->xendit_invoice_id, $revenue->external_id);
                    $this->info("  ✓ Revenue #{$revenue->id} was actually PAID — confirmed instead of expiring.");
                    $saved++;
                    continue;
                }
            } catch (\Throwable $e) {
                Log::warning("[SubscriptionExpirePendingPayments] Failed to check invoice for revenue #{$revenue->id}: " . $e->getMessage());
                // Gagal cek ke Xendit (mis. API down) — tetap expire-kan
                // berdasarkan waktu lokal, supaya siswa gak nyangkut
                // "pending" selamanya kalau Xendit sedang bermasalah.
            }

            $xendit->markExpired($revenue->xendit_invoice_id, $revenue->external_id);
            $this->info("  ⏰ Revenue #{$revenue->id} expired.");
            $expired++;
        }

        $this->info("Done. Expired: {$expired}, Saved (actually paid): {$saved}.");
        Log::info("[SubscriptionExpirePendingPayments] Completed. Expired={$expired}, Saved={$saved}");

        return Command::SUCCESS;
    }
}
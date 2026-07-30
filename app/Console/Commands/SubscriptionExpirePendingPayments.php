<?php

namespace App\Console\Commands;

use App\Services\MidtransService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SubscriptionExpirePendingPayments extends Command
{
    protected $signature = 'subscription:expire-pending-payments';

    protected $description = 'Expire pending Midtrans QRIS transactions that have passed their payment window — safety net for when the payer closes the QRIS modal (stops polling) and the Midtrans webhook never arrives.';

    public function handle(MidtransService $paymentGateway): int
    {
        $result = $paymentGateway->sweepExpiredPending();

        $this->info("Checked {$result['checked']} pending payment(s) past their expiry window.");
        $this->info("Done. Expired: {$result['expired']}, Saved (actually paid): {$result['saved']}.");

        Log::info("[SubscriptionExpirePendingPayments] Completed. Expired={$result['expired']}, Saved={$result['saved']}");

        return Command::SUCCESS;
    }
}
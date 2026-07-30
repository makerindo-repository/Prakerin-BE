<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BUG FIX KRITIS: seluruh kode Midtrans (MidtransService, SubscriptionController,
 * RevenueController) sudah ditulis dengan asumsi kolom bernama
 * `payment_reference_id` — tapi migration untuk benar-benar merename kolom
 * lama `xendit_invoice_id` ke nama itu ternyata belum pernah dibuat.
 *
 * Tanpa migration ini, setiap query yang menyentuh kolom itu (createPayment,
 * confirmPayment, markExpired, sweepExpiredPending, dashboard admin, dst)
 * akan gagal dengan SQL error "Unknown column 'payment_reference_id'".
 *
 * Dipakai raw SQL (CHANGE COLUMN) karena doctrine/dbal belum terinstall,
 * jadi Blueprint::renameColumn()/->change() belum bisa dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE revenue CHANGE xendit_invoice_id payment_reference_id VARCHAR(255) NULL");
        DB::statement("ALTER TABLE subscriptions CHANGE xendit_invoice_id payment_reference_id VARCHAR(255) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE revenue CHANGE payment_reference_id xendit_invoice_id VARCHAR(255) NULL");
        DB::statement("ALTER TABLE subscriptions CHANGE payment_reference_id xendit_invoice_id VARCHAR(255) NULL");
    }
};

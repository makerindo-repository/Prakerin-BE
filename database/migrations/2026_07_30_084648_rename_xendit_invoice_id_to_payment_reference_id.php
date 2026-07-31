<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
 * Pakai Schema::table()->renameColumn() (BUKAN raw "ALTER TABLE ... CHANGE"
 * ala MySQL) supaya jalan di SQLite (lokal/Laragon) MAUPUN MySQL (server) —
 * Laravel 11+ sudah native support renameColumn() tanpa perlu package
 * doctrine/dbal sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue', function (Blueprint $table) {
            $table->renameColumn('xendit_invoice_id', 'payment_reference_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('xendit_invoice_id', 'payment_reference_id');
        });
        if (Schema::hasColumn('revenue', 'xendit_invoice_id')) {
            Schema::table('revenue', function (Blueprint $table) {
                $table->renameColumn('xendit_invoice_id', 'payment_reference_id');
            });
        }

        if (Schema::hasColumn('subscriptions', 'xendit_invoice_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->renameColumn('xendit_invoice_id', 'payment_reference_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('revenue', 'payment_reference_id')) {
            Schema::table('revenue', function (Blueprint $table) {
                $table->renameColumn('payment_reference_id', 'xendit_invoice_id');
            });
        }

        if (Schema::hasColumn('subscriptions', 'payment_reference_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->renameColumn('payment_reference_id', 'xendit_invoice_id');
            });
        }
    }
};
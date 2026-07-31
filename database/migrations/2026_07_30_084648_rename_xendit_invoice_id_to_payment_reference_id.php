<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

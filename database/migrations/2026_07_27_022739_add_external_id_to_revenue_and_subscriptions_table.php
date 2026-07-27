<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue', function (Blueprint $table) {
            // external_id = referenceId yang KITA generate sendiri sebelum
            // manggil Xendit (bukan xendit_invoice_id yang baru ada SETELAH
            // API call sukses). Disimpan lebih awal supaya webhook tetap bisa
            // mencocokkan record ini walaupun response createInvoice gagal
            // sampai ke server (timeout/crash) — xendit_invoice_id jadi tidak
            // wajib untuk proses matching, cuma nice-to-have.
            $table->string('external_id', 255)->nullable()->after('xendit_invoice_id');
            $table->index('external_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('external_id', 255)->nullable()->after('xendit_invoice_id');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('revenue', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};

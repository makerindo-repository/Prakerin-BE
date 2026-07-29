<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue', function (Blueprint $table) {
            // Sebelumnya invoice_url/qr_code_url cuma ada sesaat di response
            // HTTP createPayment(), tidak pernah disimpan — jadi begitu user
            // menutup modal QRIS (sengaja/tidak sengaja), data itu hilang
            // total dan klik "beli paket" berikutnya selalu bikin invoice
            // Xendit BARU (menyisakan invoice lama nganggur berstatus pending).
            // Dengan disimpan di sini, createPayment() bisa cek & pakai ulang
            // invoice yang masih pending & belum expired alih-alih bikin baru.
            $table->string('invoice_url', 500)->nullable()->after('xendit_invoice_id');
            $table->string('qr_code_url', 1000)->nullable()->after('invoice_url');
            $table->timestamp('expiry_date')->nullable()->after('qr_code_url');
        });
    }

    public function down(): void
    {
        Schema::table('revenue', function (Blueprint $table) {
            $table->dropColumn(['invoice_url', 'qr_code_url', 'expiry_date']);
        });
    }
};

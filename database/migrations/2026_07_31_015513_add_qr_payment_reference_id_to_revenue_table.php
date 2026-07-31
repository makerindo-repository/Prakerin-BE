<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue', function (Blueprint $table) {
            // Sekarang 1 invoice logis bisa punya 2 transaksi Midtrans
            // paralel: `payment_reference_id` (Snap — link "metode lain":
            // bank/e-wallet/Indomaret/dll) tetap jadi kolom utama, dan
            // `qr_payment_reference_id` ini buat transaksi Core API QRIS
            // langsung (best-effort, ditampilkan sebagai QR di modal kita).
            // Siswa bisa bayar lewat SALAH SATU — begitu salah satunya
            // lunas, invoice ini dianggap lunas (lihat MidtransService).
            $table->string('qr_payment_reference_id', 255)->nullable()->after('payment_reference_id');
            $table->index('qr_payment_reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('revenue', function (Blueprint $table) {
            $table->dropIndex(['qr_payment_reference_id']);
            $table->dropColumn('qr_payment_reference_id');
        });
    }
};

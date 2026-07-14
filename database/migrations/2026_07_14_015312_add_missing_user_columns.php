<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * NOTE: `last_login_at` dan `is_pro` sudah ada di file migration
     * `0001_01_01_000000_create_users_table.php`, tapi migration itu sudah
     * pernah dijalankan di beberapa environment (mis. production) SEBELUM
     * kedua kolom itu ditambahkan ke filenya — jadi Laravel menganggapnya
     * sudah selesai dan tidak menjalankan ulang, sehingga kolomnya tidak
     * pernah benar-benar dibuat di sana. Migration ini menambahkannya
     * secara terpisah & aman (cek dulu apakah kolom sudah ada) supaya
     * environment mana pun — sudah punya kolomnya atau belum — jadi
     * konsisten.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'is_pro')) {
                $table->boolean('is_pro')->default(false)->after('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users', 'is_pro')) {
                $table->dropColumn('is_pro');
            }
        });
    }
};
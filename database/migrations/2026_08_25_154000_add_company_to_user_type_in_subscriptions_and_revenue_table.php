<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('user_type', 50)->change();
        });

        Schema::table('revenue', function (Blueprint $table) {
            $table->string('user_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('user_type', ['siswa', 'mahasiswa'])->change();
        });

        Schema::table('revenue', function (Blueprint $table) {
            $table->enum('user_type', ['siswa', 'mahasiswa'])->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Strategy: rename `status` → `status_magang` (preserving data),
     * add `status_subscription` and `subscription_renewed_at`.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add the new subscription columns
            $table->enum('status_subscription', ['free', 'premium'])->default('free')->after('status');
            $table->timestamp('subscription_renewed_at')->nullable()->after('status_subscription');
        });

        // Rename status → status_magang by adding new column, copying data, dropping old
        // MySQL does not support RENAME COLUMN for ENUM in older versions; we use a safe approach.
        Schema::table('students', function (Blueprint $table) {
            $table->enum('status_magang', ['not_started', 'ongoing', 'completed'])->default('not_started')->after('status');
        });

        // Copy existing data from status → status_magang
        DB::statement('UPDATE students SET status_magang = status');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('status', ['not_started', 'ongoing', 'completed'])->default('not_started');
        });

        DB::statement('UPDATE students SET status = status_magang');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['status_magang', 'status_subscription', 'subscription_renewed_at']);
        });
    }
};

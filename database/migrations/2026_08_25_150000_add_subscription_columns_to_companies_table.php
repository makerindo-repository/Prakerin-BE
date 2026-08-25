<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('status_subscription', ['free', 'premium'])->default('free')->after('is_verified');
            $table->timestamp('subscription_renewed_at')->nullable()->after('status_subscription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['status_subscription', 'subscription_renewed_at']);
        });
    }
};

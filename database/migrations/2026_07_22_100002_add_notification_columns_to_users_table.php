<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email_notifications_enabled')) {
                $table->boolean('email_notifications_enabled')->default(true)->after('is_pro');
            }
            if (!Schema::hasColumn('users', 'whatsapp_notifications_enabled')) {
                $table->boolean('whatsapp_notifications_enabled')->default(false)->after('email_notifications_enabled');
            }
            if (!Schema::hasColumn('users', 'whatsapp_number')) {
                $table->string('whatsapp_number', 20)->nullable()->after('whatsapp_notifications_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'email_notifications_enabled')) {
                $table->dropColumn('email_notifications_enabled');
            }
            if (Schema::hasColumn('users', 'whatsapp_notifications_enabled')) {
                $table->dropColumn('whatsapp_notifications_enabled');
            }
            if (Schema::hasColumn('users', 'whatsapp_number')) {
                $table->dropColumn('whatsapp_number');
            }
        });
    }
};

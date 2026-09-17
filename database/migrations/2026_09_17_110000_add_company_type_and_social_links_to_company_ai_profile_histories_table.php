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
        if (Schema::hasTable('company_ai_profile_histories')) {
            Schema::table('company_ai_profile_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('company_ai_profile_histories', 'company_type')) {
                    $table->string('company_type', 10)->nullable()->after('company_name');
                }
                if (!Schema::hasColumn('company_ai_profile_histories', 'social_links')) {
                    $table->json('social_links')->nullable()->after('linkedin');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('company_ai_profile_histories')) {
            Schema::table('company_ai_profile_histories', function (Blueprint $table) {
                if (Schema::hasColumn('company_ai_profile_histories', 'social_links')) {
                    $table->dropColumn('social_links');
                }
                if (Schema::hasColumn('company_ai_profile_histories', 'company_type')) {
                    $table->dropColumn('company_type');
                }
            });
        }
    }
};

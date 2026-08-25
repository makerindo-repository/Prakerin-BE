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
        Schema::create('company_ai_profile_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->string('company_name');
            $table->string('tagline')->nullable();
            $table->text('about_company')->nullable();
            $table->string('sector')->nullable();
            $table->string('established_year')->nullable();
            $table->string('employee_count')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('linkedin')->nullable();
            $table->text('address')->nullable();
            $table->text('vision')->nullable();
            $table->text('mission')->nullable();
            $table->json('competencies')->nullable();
            $table->json('portfolios')->nullable();
            $table->integer('completeness_score')->default(85);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_ai_profile_histories');
    }
};

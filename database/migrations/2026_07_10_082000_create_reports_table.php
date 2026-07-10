<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['internship_stats', 'student_progress', 'company_performance']);
            $table->json('data');
            $table->dateTime('generated_at');
            $table->uuid('generated_by_id');
            $table->timestamps();

            $table->foreign('generated_by_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('created_by_id');
            $table->enum('type', ['internship_stats', 'student_progress', 'company_performance']);
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->string('email_recipients'); // Store JSON array of email addresses
            $table->dateTime('last_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('reports');
    }
};

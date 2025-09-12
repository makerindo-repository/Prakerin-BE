<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('field_id');
            $table->uuid('duration_id');

            $table->string('title');
            $table->text('description');
            $table->enum('grade', ['smk', 'mahasiswa', 'all'])->default('all');
            $table->enum('type', ['full_time', 'part_time']);
            $table->enum('location', ['onsite', 'remote', 'hybrid', 'field']);
            $table->integer('qouta')->default(1);
            $table->boolean('is_paid');
            $table->boolean('is_available');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('field_id')->references('id')->on('fields')->onDelete('cascade');
            $table->foreign('duration_id')->references('id')->on('durations')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_openings');
    }
};

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
        Schema::create('internship_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('curriculum_vitae_id');
            $table->uuid('job_opening_id');

            $table->enum('status', ['pending', 'in_progress', 'accepted', 'rejected'])->default('pending');
            $table->enum('step', ['cv_submitted', 'theory_test', 'practice_test']);

            $table->foreign('curriculum_vitae_id')->references('id')->on('curriculum_vitaes')->onDelete('cascade');
            $table->foreign('job_opening_id')->references('id')->on('job_openings')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_applications');
    }
};

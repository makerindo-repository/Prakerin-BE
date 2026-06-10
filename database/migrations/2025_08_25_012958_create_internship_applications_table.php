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

            $table->enum('status', ['in_progress', 'accepted', 'rejected'])->default('in_progress');
            $table->integer('rating'); //there is a successful internship in the admindashboardcontroller where it looks for internship application with rating over 4 yet there is no such column?
            $table->text('cover_letter');
            $table->text('message_rejected')->nullable();

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

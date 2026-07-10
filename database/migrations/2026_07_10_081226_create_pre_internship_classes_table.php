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
        Schema::create('pre_internship_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('capacity');
            $table->enum('level', ['beginner', 'intermediate', 'advanced']);
            $table->enum('status', ['scheduled', 'ongoing', 'completed'])->default('scheduled');
            $table->uuid('created_by_id');
            $table->enum('created_by_type', ['school', 'company']);
            $table->timestamps();

            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_internship_classes');
    }
};

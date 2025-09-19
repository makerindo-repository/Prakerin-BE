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
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('school_id')->nullable();
            $table->uuid('major_id')->nullable();

            $table->string('name');
            $table->enum('status', ['not_started', 'ongoing', 'completed'])->default('not_started');
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->enum('class', ['10', '11', '12', 'college'])->nullable();
            $table->string('skill')->nullable();
            $table->string('portofolio_link')->nullable();
            $table->string('social_media_link')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete("set null");
            $table->foreign('major_id')->references('id')->on('majors')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

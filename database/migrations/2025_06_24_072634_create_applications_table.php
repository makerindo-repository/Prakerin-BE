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
        // Schema::create('applications', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('student_id')->constrained()->onDelete('cascade');
        //     $table->foreignId('internship_id')->constrained()->onDelete('cascade');
        //     $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
        //     $table->enum('step',['cv_submitted', 'teory_test', 'praktik_test'])->default('cv_submitted');
        //     $table->string('cv');
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};

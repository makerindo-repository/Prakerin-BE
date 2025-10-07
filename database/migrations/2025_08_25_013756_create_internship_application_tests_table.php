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
        Schema::create('internship_application_test', function (Blueprint $table) {
            $table->uuid('internship_application_id');
            $table->uuid('test_id');
            // $table->enum('type', ['theory', 'practice']);
            $table->boolean('is_passed')->default(false);
            $table->timestamps();
            $table->primary(['internship_application_id', 'test_id']);
            $table->foreign('internship_application_id')->references('id')->on('internship_applications')->onDelete('cascade');
            $table->foreign('test_id')->references('id')->on('tests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_application_tests');
    }
};

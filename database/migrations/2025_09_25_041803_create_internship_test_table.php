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
        Schema::create('internship_test', function (Blueprint $table) {
            $table->uuid('internship_id');
            $table->uuid('test_id');
            $table->timestamps();
            $table->primary(['internship_id', 'test_id']);
            $table->foreign('internship_id')->references('id')->on('internships')->onDelete('cascade');
            $table->foreign('test_id')->references('id')->on('tests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_test');
    }
};

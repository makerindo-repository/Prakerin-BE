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
        Schema::create('jobOpening_test', function (Blueprint $table) {
            $table->uuid('job_opening_id');
            $table->uuid('test_id');
            $table->timestamps();
            $table->primary(['job_opening_id', 'test_id']);
            $table->foreign('test_id')->references('id')->on('tests')->onDelete('cascade');
            $table->foreign('job_opening_id')->references('id')->on('job_openings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobOpening_test');
    }
};

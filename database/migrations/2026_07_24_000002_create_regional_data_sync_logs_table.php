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
        Schema::create('regional_data_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sync_source')->default('emsifa');
            $table->string('status')->default('pending'); // pending, success, failed
            $table->integer('provinces_created')->default(0);
            $table->integer('provinces_updated')->default(0);
            $table->integer('cities_created')->default(0);
            $table->integer('cities_updated')->default(0);
            $table->text('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_data_sync_logs');
    }
};

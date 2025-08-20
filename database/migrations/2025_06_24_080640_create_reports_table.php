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
        // Schema::create('reports', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('application_id')->constrained()->onDelete('cascade');
        //     $table->string('report_text');
        //     $table->string('report_file');
        //     $table->boolean('is_verified_by_school')->default(false);
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

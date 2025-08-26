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
        Schema::create('report_task_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_task_id');
            $table->uuid('student_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->text('message');

            $table->foreign('report_task_id')->references('id')->on('report_tasks')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_task_messages');
    }
};

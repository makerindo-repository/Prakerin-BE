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
        Schema::create('internships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('internship_application_id');
            $table->uuid('role_id')->nullable();
            $table->foreignUuid('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreignUuid('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_completed')->default(false);

            $table->foreign('internship_application_id')->references('id')->on('internship_applications')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon'); // Lucide icon name
            $table->enum('category', ['achievement', 'excellence', 'participation', 'special']);
            $table->integer('point_value')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_id');
            $table->timestamps();

            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('student_awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('award_id');
            $table->text('reason')->nullable();
            $table->dateTime('awarded_at');
            $table->uuid('awarded_by_id');
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('award_id')->references('id')->on('awards')->onDelete('cascade');
            $table->foreign('awarded_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['student_id', 'award_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_awards');
        Schema::dropIfExists('awards');
    }
};

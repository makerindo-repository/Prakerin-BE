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
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');

            $table->string('name');
            $table->text('address');
            $table->string('description')->nullable();
            $table->string('phone_number')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->enum('accreditation', ['A', 'B', 'C'])->nullable();
            $table->string('website')->nullable();
            $table->string('npsn')->nullable();
            $table->enum('status', ['negeri', 'swasta']);



            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};

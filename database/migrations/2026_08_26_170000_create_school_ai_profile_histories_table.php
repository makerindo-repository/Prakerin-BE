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
        Schema::create('school_ai_profile_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('school_id')->nullable();
            $table->string('school_name');
            $table->string('type')->nullable(); // smk, sma, university, institute, polytechnic, etc.
            $table->string('tagline')->nullable();
            $table->text('about_school')->nullable();
            $table->string('accreditation')->nullable();
            $table->string('npsn')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->text('vision')->nullable();
            $table->text('mission')->nullable();
            $table->json('majors')->nullable();
            $table->json('competencies')->nullable(); // Mata pelajaran kejuruan & kompetensi
            $table->json('facilities')->nullable(); // Sarana lab, teaching factory, workshop
            $table->json('partnerships')->nullable(); // Portofolio kemitraan industri & prestasi
            $table->integer('completeness_score')->default(85);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['school_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_ai_profile_histories');
    }
};

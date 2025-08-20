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
        // Schema::create('internships', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('title');
        //     $table->longText('description');
        //     $table->dateTime('start_date');
        //     $table->dateTime('end_date');
        //     $table->enum('grade', ['smk', 'mahasiswa', 'all'])->default('all');
        //     $table->enum('bidang', ['IT', 'Embedding', 'Other'])->default('Other');
        //     $table->enum('type', ['wfh', 'full time', 'hybrid']);
        //     $table->foreignId('company_id')->constrained()->onDelete('cascade');
        //     $table->integer('kuota')->default(3);
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
};

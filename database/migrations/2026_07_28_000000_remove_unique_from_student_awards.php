<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_awards', function (Blueprint $table) {
            $table->index('student_id', 'student_awards_student_id_index');
            $table->dropUnique('student_awards_student_id_award_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_awards', function (Blueprint $table) {
            $table->unique(['student_id', 'award_id']);
            $table->dropIndex('student_awards_student_id_index');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('class', 50)->nullable()->change();
        });

        // Update legacy 'collage' values to semester '5' (default)
        DB::table('students')->where('class', 'collage')->update(['class' => '5']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Note: reverting back to enum might fail if there are values other than 10, 11, 12, collage.
            // But we will try to update first for safety.
            DB::table('students')->whereNotIn('class', ['10', '11', '12'])->update(['class' => 'collage']);
            $table->enum('class', ['10', '11', '12', 'collage'])->nullable()->change();
        });
    }
};

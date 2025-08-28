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
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('to_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('to_type', ['student', 'company', 'school', 'super_admin']);
            $table->tinyInteger('rating')->unsigned()->comment('1-5');
            $table->text('text');
            $table->unique(['from_user_id', 'to_user_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};

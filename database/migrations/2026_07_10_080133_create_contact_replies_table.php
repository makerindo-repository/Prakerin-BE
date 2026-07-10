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
        Schema::create('contact_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_message_id');
            $table->uuid('replied_by_id');
            $table->text('reply_message');
            $table->timestamps();

            $table->foreign('contact_message_id')->references('id')->on('contact_messages')->onDelete('cascade');
            $table->foreign('replied_by_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_replies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_items', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');           // recipient user UUID
            $table->string('sender_id')->nullable(); // who triggered it (can be system)
            $table->string('title');
            $table->text('content');
            $table->string('type');              // 'application_status', 'new_task', 'report_feedback', 'new_application'
            $table->string('related_type')->nullable(); // e.g. 'InternshipApplication', 'Task', 'Report'
            $table->unsignedBigInteger('related_id')->nullable(); // FK to the related record
            $table->string('action_url')->nullable(); // deep link in frontend
            $table->boolean('is_read')->default(false);
            $table->boolean('notification_sent')->default(false); // prevent duplicate sends
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->unsignedBigInteger('inbox_item_id');
            $table->string('notification_type');          // 'application_status', 'new_task', etc.
            $table->string('channel');                    // 'email' or 'whatsapp'
            $table->string('status')->default('queued'); // queued, sent, delivered, read, failed
            $table->string('message_id')->nullable();    // provider's message ID (Twilio SID, etc.)
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('inbox_item_id')->references('id')->on('inbox_items')->onDelete('cascade');
            $table->index(['user_id', 'channel', 'status']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

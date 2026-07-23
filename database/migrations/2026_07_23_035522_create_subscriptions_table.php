<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->enum('user_type', ['siswa', 'mahasiswa']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('IDR');
            $table->enum('status', ['active', 'expired', 'pending_payment'])->default('pending_payment');
            $table->timestamp('subscription_start_date');
            $table->timestamp('subscription_end_date');
            $table->timestamp('renewal_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('xendit_invoice_id', 255)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('students')->onDelete('cascade');
            $table->index(['user_id', 'user_type']);
            $table->index('xendit_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

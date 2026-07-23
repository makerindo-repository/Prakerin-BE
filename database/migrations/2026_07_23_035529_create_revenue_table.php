<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->uuid('user_id');
            $table->enum('user_type', ['siswa', 'mahasiswa']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('IDR');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->timestamp('payment_date')->nullable();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('xendit_invoice_id', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('xendit_invoice_id');
            $table->index(['user_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue');
    }
};

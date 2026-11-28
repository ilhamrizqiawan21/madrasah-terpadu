<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log mentah append-only webhook Midtrans (PAYMENTS §4). Hanya processed_at/process_result yang diisi sekali.
        Schema::create('payment_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id', 64)->nullable();
            $table->string('transaction_status', 30)->nullable();
            $table->string('status_code', 5)->nullable();
            $table->boolean('signature_valid');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('process_result', 30)->nullable();
            $table->timestamp('received_at');

            $table->index(['order_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notifications');
    }
};

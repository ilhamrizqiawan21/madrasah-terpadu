<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id', 50)->unique();
            $table->string('purpose', 20);
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->string('status', 20);
            $table->string('snap_token', 100)->nullable();
            $table->string('snap_redirect_url', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->string('payment_type', 40)->nullable();
            $table->json('last_status_payload')->nullable();
            // Antrean "Perlu ditinjau" bendahara (T-705/T-707): nominal tidak cocok, refund/chargeback, kelebihan bayar.
            $table->string('review_reason', 30)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['student_id', 'created_at']);
            $table->index(['review_reason', 'reviewed_at']);
        });

        Schema::create('payment_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_order_id')->constrained('payment_orders')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->string('description', 100);
            $table->unsignedBigInteger('amount');

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_order_items');
        Schema::dropIfExists('payment_orders');
    }
};

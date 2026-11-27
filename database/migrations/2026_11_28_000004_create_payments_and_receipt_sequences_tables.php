<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('method', 20);
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('payment_order_id')->nullable()->unique();
            $table->string('reference', 120)->nullable();
            $table->string('receipt_number', 40)->unique();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 255)->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'paid_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->unsignedBigInteger('amount');

            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('receipt_sequences');
    }
};

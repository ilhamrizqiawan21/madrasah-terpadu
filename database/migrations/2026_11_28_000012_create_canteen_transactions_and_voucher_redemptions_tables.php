<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canteen_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->uuid('client_uuid')->unique();
            $table->bigInteger('total');
            $table->bigInteger('paid_from_vouchers')->default(0);
            $table->bigInteger('paid_from_wallet')->default(0);
            $table->json('items')->nullable();
            $table->string('status', 20)->default('completed');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['student_id', 'created_at']);
            $table->index(['cashier_id', 'created_at']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('canteen_voucher_id')->constrained('canteen_vouchers')->restrictOnDelete();
            $table->foreignId('canteen_transaction_id')->constrained('canteen_transactions')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('canteen_transactions');
    }
};

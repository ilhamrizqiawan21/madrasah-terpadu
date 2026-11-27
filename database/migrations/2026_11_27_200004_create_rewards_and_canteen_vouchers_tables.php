<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('cost_points');
            $table->unsignedBigInteger('voucher_amount');
            $table->unsignedSmallInteger('voucher_valid_days');
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'unit_id']);
        });

        Schema::create('reward_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('reward_offer_id')->constrained('reward_offers')->restrictOnDelete();
            $table->unsignedSmallInteger('cost_points');
            $table->unsignedBigInteger('voucher_amount');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id']);
            $table->index(['status']);
        });

        Schema::create('canteen_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('reward_redemption_id')->unique()->constrained('reward_redemptions')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('remaining');
            $table->date('expires_on');
            $table->timestamp('created_at');

            $table->index(['student_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canteen_vouchers');
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('reward_offers');
    }
};

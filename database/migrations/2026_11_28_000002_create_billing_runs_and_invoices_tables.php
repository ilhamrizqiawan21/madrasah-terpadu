<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->string('target_type', 20);
            $table->json('target_payload');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('amount');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->foreignId('billing_run_id')->nullable()->constrained('billing_runs')->nullOnDelete();
            $table->date('period')->nullable();
            $table->string('description', 160);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('adjustment_total')->default(0);
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->date('due_date');
            $table->string('status', 20)->default('unpaid');
            $table->string('cancelled_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'fee_type_id', 'period'], 'invoices_student_fee_period_unique');
            $table->index(['status', 'due_date']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('billing_runs');
    }
};

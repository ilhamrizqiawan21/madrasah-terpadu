<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('delivery', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'status']);
        });

        Schema::create('exam_period_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_period_id')->constrained('exam_periods')->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->date('period_until')->nullable();
            $table->string('invoice_scope', 20)->default('all_open');
            $table->timestamps();

            $table->unique(['exam_period_id', 'fee_type_id'], 'exam_period_req_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_period_requirements');
        Schema::dropIfExists('exam_periods');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('point_rule_id')->constrained('point_rules')->restrictOnDelete();
            $table->string('category', 20);
            $table->unsignedSmallInteger('points');
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->string('evidence_path', 255)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id']);
            $table->index(['occurred_on', 'status']);
        });

        Schema::create('point_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('account', 20);
            $table->integer('delta');
            $table->integer('balance_after');
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->string('idempotency_key', 120)->unique();
            $table->timestamp('created_at');

            $table->index(['student_id', 'academic_year_id', 'account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_entries');
        Schema::dropIfExists('behavior_records');
    }
};

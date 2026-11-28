<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_eligibility_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_period_id')->constrained('exam_periods')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('granted_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['exam_period_id', 'student_id', 'revoked_at'], 'exam_override_period_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_eligibility_overrides');
    }
};

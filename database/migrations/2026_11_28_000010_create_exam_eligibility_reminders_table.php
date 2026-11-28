<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_eligibility_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_period_id')->constrained('exam_periods')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('sent_date');
            $table->string('kind', 10);
            $table->timestamp('sent_at');

            $table->unique(['exam_period_id', 'student_id', 'sent_date'], 'exam_elig_reminder_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_eligibility_reminders');
    }
};

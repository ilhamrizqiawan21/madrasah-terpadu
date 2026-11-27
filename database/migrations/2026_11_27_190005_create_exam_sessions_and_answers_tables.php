<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('deadline_at');
            $table->unsignedSmallInteger('extra_minutes')->default(0);
            $table->string('status', 32)->default('in_progress');
            $table->string('device_type', 20);
            $table->string('device_token', 64);
            $table->unsignedInteger('shuffle_seed');
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedSmallInteger('takeover_count')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
            $table->index(['exam_id', 'status']);
        });

        Schema::create('exam_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained('exam_questions')->restrictOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('client_ts');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['exam_session_id', 'exam_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_sessions');
    }
};

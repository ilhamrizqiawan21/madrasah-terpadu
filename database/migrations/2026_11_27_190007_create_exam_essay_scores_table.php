<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_essay_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained('exam_questions')->restrictOnDelete();
            $table->unsignedTinyInteger('score');
            $table->foreignId('scored_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['exam_session_id', 'exam_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_essay_scores');
    }
};

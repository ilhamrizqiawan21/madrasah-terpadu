<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->string('type', 20);
            $table->text('body');
            $table->boolean('is_rtl')->default(false);
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->json('answer_key')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'sort_order']);
        });

        Schema::create('exam_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_question_id')->constrained('exam_questions')->cascadeOnDelete();
            $table->string('label', 20);
            $table->text('body');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['exam_question_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_question_options');
        Schema::dropIfExists('exam_questions');
    }
};

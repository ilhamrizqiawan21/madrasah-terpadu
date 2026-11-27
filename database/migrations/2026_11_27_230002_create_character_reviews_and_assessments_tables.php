<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penilaian karakter per rombel per semester (ADR-029, T-1203).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('computed_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'academic_year_id']);
            $table->index(['academic_year_id', 'status']);
        });

        Schema::create('character_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_review_id')->constrained('character_reviews')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('character_dimension_id')->constrained('character_dimensions')->restrictOnDelete();
            $table->decimal('computed_score', 5, 2)->nullable();
            $table->string('computed_level', 10)->nullable();
            $table->string('final_level', 10)->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('observation_level')->nullable();
            $table->text('description')->nullable();
            $table->boolean('description_edited')->default(false);
            $table->json('inputs');
            $table->timestamps();

            $table->unique(['character_review_id', 'student_id', 'character_dimension_id'], 'character_assessments_unique');
            $table->index(['student_id', 'character_review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_assessments');
        Schema::dropIfExists('character_reviews');
    }
};

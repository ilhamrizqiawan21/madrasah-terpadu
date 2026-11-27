<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('exam_period_id')->nullable()->comment('Opsional; FK ke exam_periods menyusul pada Rilis peralihan (ADR-021).');
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('title', 150);
            $table->string('grade', 10)->nullable();
            $table->json('classroom_ids');
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->json('weights');
            $table->string('access_token', 20)->nullable();
            $table->unsignedTinyInteger('violation_limit')->default(3);
            $table->json('device_modes');
            $table->boolean('show_result_to_guardian')->default(false);
            $table->string('status', 32)->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['subject_id', 'status']);
            $table->index(['opens_at', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};

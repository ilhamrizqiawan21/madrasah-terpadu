<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_thresholds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->unsignedSmallInteger('points');
            $table->string('action_name', 120);
            $table->boolean('notify_guardian')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['unit_id', 'points']);
        });

        Schema::create('discipline_followups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('discipline_threshold_id')->constrained('discipline_thresholds')->restrictOnDelete();
            $table->timestamp('triggered_at');
            $table->string('status', 20)->default('open');
            $table->foreignId('done_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id', 'discipline_threshold_id'], 'discipline_followups_unique_trigger');
            $table->index(['status', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_followups');
        Schema::dropIfExists('discipline_thresholds');
    }
};

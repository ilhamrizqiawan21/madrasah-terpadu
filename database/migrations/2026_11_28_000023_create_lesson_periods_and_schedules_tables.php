<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akademik > Jam Pelajaran dan Jadwal Pelajaran Mingguan (alur aplikasi lama).
        Schema::create('lesson_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 30);
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_break')->default(false);
            $table->timestamps();
            $table->unique(['unit_id', 'name']);
        });

        Schema::create('lesson_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->foreignId('lesson_period_id')->constrained('lesson_periods')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->timestamps();

            $table->unique(['classroom_id', 'weekday', 'lesson_period_id']);
            $table->index(['teacher_id', 'weekday', 'lesson_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_schedules');
        Schema::dropIfExists('lesson_periods');
    }
};

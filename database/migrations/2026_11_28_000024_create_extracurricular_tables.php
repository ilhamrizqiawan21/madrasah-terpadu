<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akademik > Ekstrakurikuler, Anggota & Nilai Ekskul (alur aplikasi lama).
        Schema::create('extracurriculars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('coach_type', 10);
            $table->foreignId('coach_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('coach_name', 100)->nullable();
            $table->timestamps();
            $table->unique(['unit_id', 'name']);
        });

        Schema::create('extracurricular_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extracurricular_id')->constrained('extracurriculars')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('grade', 1)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['extracurricular_id', 'student_id', 'academic_year_id'], 'ekskul_member_unique');
        });

        Schema::create('extracurricular_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extracurricular_member_id')->constrained('extracurricular_members')->cascadeOnDelete();
            $table->date('met_on');
            $table->string('status', 10);
            $table->timestamps();
            $table->unique(['extracurricular_member_id', 'met_on'], 'ekskul_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracurricular_attendances');
        Schema::dropIfExists('extracurricular_members');
        Schema::dropIfExists('extracurriculars');
    }
};

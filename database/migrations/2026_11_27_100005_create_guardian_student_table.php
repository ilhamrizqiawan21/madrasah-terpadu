<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_student', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('relation', 10);
            // Tepat satu wali utama per siswa dijaga GuardianService (dalam transaksi), bukan indeks.
            $table->boolean('is_primary')->default(false);

            $table->unique(['guardian_id', 'student_id']);
            $table->index(['student_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
    }
};

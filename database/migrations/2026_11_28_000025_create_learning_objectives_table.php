<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akademik > Tujuan Pembelajaran (TP) per mapel dan tingkat kelas; tetap berlaku lintas tahun ajaran.
        Schema::create('learning_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('grade', 20);
            $table->string('code', 30);
            $table->text('description');
            $table->timestamps();
            $table->unique(['subject_id', 'grade', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_objectives');
    }
};

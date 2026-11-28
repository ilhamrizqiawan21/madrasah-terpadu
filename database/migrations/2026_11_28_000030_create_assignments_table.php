<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->dateTime('due_at');
            $table->string('attachment_path', 255)->nullable();
            $table->timestamps();

            $table->index(['classroom_id', 'due_at']);
            $table->index(['teacher_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};

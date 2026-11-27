<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_mutations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->date('mutated_on');
            $table->string('school_name', 120)->nullable();
            $table->string('reason', 255);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'mutated_on']);
            $table->index(['type', 'mutated_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_mutations');
    }
};

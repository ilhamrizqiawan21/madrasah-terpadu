<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracer study alumni (Pengguna > Survei & Data Alumni, alur aplikasi lama). Satu jawaban per alumni.
        Schema::create('alumni_tracer_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->restrictOnDelete();
            $table->unsignedSmallInteger('graduation_year');
            $table->string('activity', 20);
            $table->string('institution', 150)->nullable();
            $table->string('program', 120)->nullable();
            $table->string('income_range', 20)->nullable();
            $table->string('relevance', 20);
            $table->text('feedback')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['activity', 'graduation_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_tracer_responses');
    }
};

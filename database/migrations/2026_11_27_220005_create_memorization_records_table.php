<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorization_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('rubric_version_id')->constrained('rubric_versions')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('type', 20)->nullable();
            $table->foreignId('memorization_material_id')->nullable()->constrained('memorization_materials')->restrictOnDelete();
            $table->unsignedTinyInteger('surah_from')->nullable();
            $table->unsignedSmallInteger('ayah_from')->nullable();
            $table->unsignedTinyInteger('surah_to')->nullable();
            $table->unsignedSmallInteger('ayah_to')->nullable();
            $table->json('scores');
            $table->decimal('final_score', 5, 2);
            $table->string('predicate', 2);
            $table->boolean('passed');
            $table->text('note')->nullable();
            $table->date('recorded_on');
            $table->timestamps();

            $table->index(['student_id', 'recorded_on']);
            $table->index(['kind', 'passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memorization_records');
    }
};

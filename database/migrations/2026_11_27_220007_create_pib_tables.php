<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pib_chapters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('pib_subchapters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pib_chapter_id')->constrained('pib_chapters')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('pib_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pib_subchapter_id')->constrained('pib_subchapters')->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('pib_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('pib_material_id')->constrained('pib_materials')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->unsignedTinyInteger('mistakes');
            $table->unsignedTinyInteger('score');
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'pib_material_id', 'academic_year_id'], 'pib_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pib_scores');
        Schema::dropIfExists('pib_materials');
        Schema::dropIfExists('pib_subchapters');
        Schema::dropIfExists('pib_chapters');
    }
};

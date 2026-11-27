<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rubric_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rubric_id')->constrained('rubrics')->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->decimal('pass_threshold', 5, 2)->default(75.00);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['rubric_id', 'version_number']);
        });

        Schema::create('rubric_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rubric_version_id')->constrained('rubric_versions')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('mode', 20);
            $table->decimal('weight', 5, 2);
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->decimal('deduction_per_error', 5, 2)->default(0);
            $table->decimal('pass_threshold', 5, 2)->default(75.00);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('rubric_versions');
        Schema::dropIfExists('rubrics');
    }
};

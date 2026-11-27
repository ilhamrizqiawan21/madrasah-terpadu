<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi penilaian karakter per unit (ADR-029, T-1201).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained('units')->restrictOnDelete();
            $table->string('curriculum', 20);
            $table->json('scale');
            $table->unsignedTinyInteger('coverage_min_pct')->default(60);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('character_dimensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('description_templates');
            $table->timestamps();

            $table->unique(['unit_id', 'code']);
            $table->index(['unit_id', 'is_active', 'position']);
        });

        Schema::create('character_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_dimension_id')->constrained('character_dimensions')->cascadeOnDelete();
            $table->string('source', 30);
            $table->unsignedTinyInteger('weight');
            $table->json('params');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_components');
        Schema::dropIfExists('character_dimensions');
        Schema::dropIfExists('character_profiles');
    }
};

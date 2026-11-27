<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorization_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('grade', 10);
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('title', 180);
            $table->json('ranges')->nullable();
            $table->json('material_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['unit_id', 'grade', 'academic_year_id', 'kind'], 'memorization_targets_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memorization_targets');
    }
};

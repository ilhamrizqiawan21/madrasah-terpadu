<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master Akademik: Tingkat Kelas, Rombel (nama kelompok), Jurusan per unit (alur aplikasi lama).
        Schema::create('grade_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['unit_id', 'name']);
        });

        Schema::create('class_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 30);
            $table->timestamps();
            $table->unique(['unit_id', 'name']);
        });

        Schema::create('majors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 60);
            $table->timestamps();
            $table->unique(['unit_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('majors');
        Schema::dropIfExists('class_groups');
        Schema::dropIfExists('grade_levels');
    }
};

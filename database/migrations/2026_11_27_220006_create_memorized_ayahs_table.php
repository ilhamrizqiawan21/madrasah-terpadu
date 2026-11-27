<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorized_ayahs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->unsignedTinyInteger('surah');
            $table->unsignedSmallInteger('ayah');
            $table->foreignId('first_passed_record_id')->constrained('memorization_records')->restrictOnDelete();

            $table->unique(['student_id', 'surah', 'ayah'], 'memorized_ayahs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memorized_ayahs');
    }
};

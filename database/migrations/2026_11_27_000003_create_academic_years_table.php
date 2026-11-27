<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 9);
            $table->string('semester', 6);
            $table->date('starts_on');
            $table->date('ends_on');
            // Maksimal satu aktif dijaga AcademicYearService (lock + transaksi), bukan constraint DB, agar portabel.
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['name', 'semester']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};

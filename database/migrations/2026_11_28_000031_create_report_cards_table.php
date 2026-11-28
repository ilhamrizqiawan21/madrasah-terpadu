<?php

declare(strict_types=1);

use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Student::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(AcademicYear::class)->constrained()->cascadeOnDelete();
            $table->string('semester', 8);
            $table->string('file_path');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id', 'semester']);
            $table->index(['academic_year_id', 'semester', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};

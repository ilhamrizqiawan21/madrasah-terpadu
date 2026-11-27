<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('scanned_at');
            $table->date('school_date');
            $table->string('direction', 8);
            $table->string('result', 24);
            $table->string('device_label', 50)->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_date', 'student_id']);
            $table->index(['scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_scans');
    }
};

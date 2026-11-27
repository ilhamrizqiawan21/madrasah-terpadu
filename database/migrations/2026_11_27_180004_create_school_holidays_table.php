<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('title', 120);
            $table->boolean('is_day_off')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'holiday_date']);
            $table->index(['holiday_date', 'is_day_off']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_holidays');
    }
};

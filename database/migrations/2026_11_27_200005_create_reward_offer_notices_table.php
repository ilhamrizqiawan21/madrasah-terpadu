<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_offer_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('reward_offer_id')->constrained('reward_offers')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->timestamp('notified_at');

            $table->unique(['student_id', 'reward_offer_id', 'academic_year_id'], 'reward_offer_notices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_offer_notices');
    }
};

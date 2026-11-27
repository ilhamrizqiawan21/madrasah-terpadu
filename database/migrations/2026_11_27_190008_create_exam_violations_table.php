<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_violations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->timestamp('occurred_at');
            $table->boolean('counted')->default(true);

            $table->index(['exam_session_id', 'counted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_violations');
    }
};

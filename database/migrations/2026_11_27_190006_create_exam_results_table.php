<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_session_id')->unique()->constrained('exam_sessions')->cascadeOnDelete();
            $table->unsignedInteger('auto_points')->default(0);
            $table->unsignedInteger('essay_points')->nullable();
            $table->unsignedInteger('max_points')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('status', 20);
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};

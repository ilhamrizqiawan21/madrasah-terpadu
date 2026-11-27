<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('habit_item_id')->constrained('habit_items')->restrictOnDelete();
            $table->date('log_date');
            $table->boolean('done')->default(false);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->string('verification', 20)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'habit_item_id', 'log_date'], 'habit_logs_unique_entry');
            $table->index(['log_date', 'verification']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
    }
};

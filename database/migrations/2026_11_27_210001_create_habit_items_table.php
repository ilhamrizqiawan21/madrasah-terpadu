<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('aspect', 20);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->index(['unit_id', 'sort_order']);
            $table->index(['aspect', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_items');
    }
};

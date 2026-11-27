<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('name', 120);
            $table->string('category', 30);
            $table->string('recurrence', 20);
            $table->unsignedBigInteger('default_amount')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['unit_id', 'is_active']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_types');
    }
};

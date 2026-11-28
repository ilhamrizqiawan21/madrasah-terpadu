<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akademik > Gedung dan Ruangan & Lab (alur aplikasi lama).
        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['unit_id', 'code']);
        });

        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->timestamps();

            $table->unique(['building_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('buildings');
    }
};

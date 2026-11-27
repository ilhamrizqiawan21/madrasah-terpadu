<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surahs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('name_latin', 80);
            $table->string('name_arabic', 80);
            $table->unsignedSmallInteger('ayah_count');
            $table->unsignedTinyInteger('juz_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surahs');
    }
};

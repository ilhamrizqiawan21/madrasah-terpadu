<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kantin > Kategori Menu (dipakai bersama Koperasi lewat kolom module) dan Daftar Menu.
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('module', 12);
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['unit_id', 'module', 'name']);
        });

        Schema::create('canteen_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('price');
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['unit_id', 'name']);
            $table->index(['unit_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canteen_products');
        Schema::dropIfExists('product_categories');
    }
};

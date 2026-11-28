<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ADR-030: pos akun (COA), aturan alokasi dana, dan buku kas umum append-only.
        Schema::create('cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('name', 100);
            $table->string('direction', 3);
            $table->string('system_key', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['unit_id', 'name']);
            $table->unique(['unit_id', 'system_key']);
        });

        Schema::create('cash_account_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_account_id')->constrained('cash_accounts')->cascadeOnDelete();
            $table->foreignId('income_account_id')->constrained('cash_accounts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['expense_account_id', 'income_account_id'], 'cash_alloc_unique');
        });

        Schema::create('cash_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->date('entry_date');
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->string('direction', 3);
            $table->unsignedBigInteger('amount');
            $table->string('cash_box', 4);
            // Pos pendapatan yang menanggung entri ini: entri masuk = dirinya sendiri, entri keluar = sumber dana.
            $table->foreignId('funding_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->string('reference_no', 50)->nullable();
            $table->string('counterparty', 120)->nullable();
            $table->string('description', 255);
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reverses_entry_id')->nullable()->unique()->constrained('cash_entries')->restrictOnDelete();
            $table->string('reversal_reason', 255)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['unit_id', 'entry_date', 'id']);
            $table->index(['funding_account_id', 'direction']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('cash_account_allocations');
        Schema::dropIfExists('cash_accounts');
    }
};

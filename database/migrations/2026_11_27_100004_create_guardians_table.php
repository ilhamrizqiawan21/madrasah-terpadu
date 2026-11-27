<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            // Satu akun login per wali; akun tidak boleh terhapus selama data walinya ada.
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('relation', 10)->default('wali');
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akademik > Peminjaman Ruangan & Lab. Jam disimpan HH:MM waktu sekolah (WITA).
        Schema::create('room_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('used_on');
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);
            $table->string('purpose', 150)->nullable();
            $table->timestamps();
            $table->index(['room_id', 'used_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bookings');
    }
};

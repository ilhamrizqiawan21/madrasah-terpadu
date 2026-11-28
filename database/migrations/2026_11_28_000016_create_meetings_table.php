<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agenda rapat & notulen (Pengguna > Undangan Rapat, alur aplikasi lama).
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('title', 150);
            $table->date('held_on');
            $table->string('audience', 20);
            $table->text('minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['held_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('kind', 16)->default('qr');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_cards');
    }
};

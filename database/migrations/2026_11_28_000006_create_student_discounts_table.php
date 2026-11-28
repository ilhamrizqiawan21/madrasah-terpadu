<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('value');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('reason', 255);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'fee_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_discounts');
    }
};

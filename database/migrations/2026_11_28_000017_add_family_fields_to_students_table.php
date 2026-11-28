<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Biodata siswa mengikuti form "Edit Biodata Siswa" aplikasi lama.
        Schema::table('students', function (Blueprint $table): void {
            $table->string('family_card_number', 16)->nullable()->after('nisn');
            $table->string('religion', 20)->nullable()->after('birth_date');
            $table->string('father_name', 100)->nullable()->after('address');
            $table->string('father_occupation', 60)->nullable()->after('father_name');
            $table->string('mother_name', 100)->nullable()->after('father_occupation');
            $table->string('mother_occupation', 60)->nullable()->after('mother_name');

            $table->index('family_card_number');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropIndex(['family_card_number']);
            $table->dropColumn(['family_card_number', 'religion', 'father_name', 'father_occupation', 'mother_name', 'mother_occupation']);
        });
    }
};

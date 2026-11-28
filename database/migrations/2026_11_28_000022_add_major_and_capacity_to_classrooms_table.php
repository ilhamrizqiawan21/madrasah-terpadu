<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kelas & Wali Kelas (aplikasi lama): jurusan opsional dan kuota siswa per rombel.
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->foreignId('major_id')->nullable()->after('name')->constrained('majors')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->nullable()->after('major_id');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('major_id');
            $table->dropColumn('capacity');
        });
    }
};

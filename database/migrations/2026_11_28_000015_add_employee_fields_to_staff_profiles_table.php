<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Biodata pegawai mengikuti form "Tambah Pegawai" aplikasi lama (standar Dapodik).
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->string('title_prefix', 20)->nullable()->after('user_id');
            $table->string('title_suffix', 40)->nullable()->after('title_prefix');
            $table->string('nik', 16)->nullable()->after('nip');
            $table->string('nuptk', 16)->nullable()->after('nik');
            $table->string('gender', 1)->nullable()->after('nuptk');
            $table->string('birth_place', 60)->nullable()->after('gender');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->string('address', 255)->nullable()->after('birth_date');
            $table->string('assignment_type', 20)->nullable()->after('position');
            $table->string('employment_status', 10)->nullable()->after('assignment_type');
            $table->unsignedBigInteger('base_salary')->default(0)->after('employment_status');
            $table->unsignedBigInteger('allowance')->default(0)->after('base_salary');
            $table->unsignedBigInteger('hourly_rate')->default(0)->after('allowance');
            $table->string('photo_path', 255)->nullable()->after('hourly_rate');

            $table->index('assignment_type');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->dropIndex(['assignment_type']);
            $table->dropColumn([
                'title_prefix', 'title_suffix', 'nik', 'nuptk', 'gender', 'birth_place', 'birth_date', 'address',
                'assignment_type', 'employment_status', 'base_salary', 'allowance', 'hourly_rate', 'photo_path',
            ]);
        });
    }
};

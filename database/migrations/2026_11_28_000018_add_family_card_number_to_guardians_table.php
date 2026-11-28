<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No. KK wali mengikuti form "Tambah Akun Orang Tua" aplikasi lama.
        Schema::table('guardians', function (Blueprint $table): void {
            $table->string('family_card_number', 16)->nullable()->after('relation');
            $table->index('family_card_number');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->dropIndex(['family_card_number']);
            $table->dropColumn('family_card_number');
        });
    }
};

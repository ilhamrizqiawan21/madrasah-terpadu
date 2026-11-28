<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Presensi satu anggota pada satu pertemuan ekskul (hadir/izin/alpa).
 *
 * @property int $id
 * @property int $extracurricular_member_id
 * @property CarbonImmutable $met_on
 * @property string $status
 */
#[Fillable(['extracurricular_member_id', 'met_on', 'status'])]
class ExtracurricularAttendance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['met_on' => 'immutable_date'];
    }
}

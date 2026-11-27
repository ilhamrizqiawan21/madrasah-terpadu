<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Semester;
use Carbon\CarbonImmutable;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tahun ajaran + semester. Ditulis hanya lewat App\Services\AcademicYears\AcademicYearService.
 *
 * @property int $id
 * @property string $name
 * @property Semester $semester
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'semester', 'starts_on', 'ends_on', 'is_active'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    public function label(): string
    {
        return "{$this->name} {$this->semester->label()}";
    }
}

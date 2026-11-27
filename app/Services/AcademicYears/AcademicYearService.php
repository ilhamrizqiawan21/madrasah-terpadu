<?php

declare(strict_types=1);

namespace App\Services\AcademicYears;

use App\Exceptions\AcademicYearGuardException;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis tahun ajaran (T-203). Menjaga paling banyak satu tahun ajaran aktif.
 */
final class AcademicYearService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, semester: string, starts_on: string, ends_on: string}  $data
     */
    public function create(array $data, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($data, $actor): AcademicYear {
            $year = AcademicYear::query()->create([...$data, 'is_active' => false]);

            $this->auditLogger->record(
                action: 'created',
                subject: $year,
                after: $this->snapshot($year),
                userId: $actor->id,
            );

            return $year;
        });
    }

    /**
     * @param  array{name: string, semester: string, starts_on: string, ends_on: string}  $data
     */
    public function update(AcademicYear $year, array $data, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($year, $data, $actor): AcademicYear {
            $before = $this->snapshot($year);

            $year->fill($data)->save();

            $this->auditLogger->record(
                action: 'updated',
                subject: $year,
                before: $before,
                after: $this->snapshot($year),
                userId: $actor->id,
            );

            return $year;
        });
    }

    /**
     * Jadikan aktif dan matikan yang lain dalam satu transaksi. Semua baris dikunci dengan
     * urutan id yang tetap, agar dua admin yang mengaktifkan bersamaan tidak saling menunggu
     * (deadlock) dan tidak menghasilkan dua tahun aktif.
     */
    public function activate(AcademicYear $year, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($year, $actor): AcademicYear {
            $all = AcademicYear::query()->orderBy('id')->lockForUpdate()->get();
            $locked = $all->firstWhere('id', $year->id) ?? throw (new ModelNotFoundException)->setModel(AcademicYear::class, [$year->id]);

            if ($locked->is_active) {
                return $locked;
            }

            $previous = $all->filter(fn (AcademicYear $item): bool => $item->is_active)->values();

            AcademicYear::query()->whereKey($previous->modelKeys())->update(['is_active' => false]);
            $locked->forceFill(['is_active' => true])->save();

            $this->auditLogger->record(
                action: 'activated',
                subject: $locked,
                before: ['previous_active_id' => $previous->first()?->id],
                after: ['is_active' => true],
                userId: $actor->id,
            );

            return $locked;
        });
    }

    /**
     * Tahun ajaran aktif atau yang sudah dipakai rombel (T-204) tidak boleh dihapus,
     * karena rombel, enrollment, dan laporan menunjuk ke sana.
     */
    public function delete(AcademicYear $year, User $actor): void
    {
        DB::transaction(function () use ($year, $actor): void {
            $locked = AcademicYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_active) {
                throw AcademicYearGuardException::cannotDeleteActive();
            }

            if (Classroom::query()->where('academic_year_id', $locked->id)->exists()) {
                throw AcademicYearGuardException::usedByClassrooms();
            }

            $this->auditLogger->record(
                action: 'deleted',
                subject: $locked,
                before: $this->snapshot($locked),
                userId: $actor->id,
            );

            $locked->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(AcademicYear $year): array
    {
        return [
            'name' => $year->name,
            'semester' => $year->semester->value,
            'starts_on' => $year->starts_on->toDateString(),
            'ends_on' => $year->ends_on->toDateString(),
            'is_active' => $year->is_active,
        ];
    }
}

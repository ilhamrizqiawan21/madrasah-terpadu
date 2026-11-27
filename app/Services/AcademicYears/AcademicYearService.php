<?php

declare(strict_types=1);

namespace App\Services\AcademicYears;

use App\Exceptions\AcademicYearGuardException;
use App\Models\AcademicYear;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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
     * Jadikan aktif dan matikan yang lain dalam satu transaksi. Baris aktif dikunci
     * agar dua admin yang mengaktifkan bersamaan tidak menghasilkan dua tahun aktif.
     */
    public function activate(AcademicYear $year, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($year, $actor): AcademicYear {
            $locked = AcademicYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_active) {
                return $locked;
            }

            $previous = AcademicYear::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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
     * Pemakaian oleh rombel/enrollment diperiksa juga sejak T-204.
     */
    public function delete(AcademicYear $year, User $actor): void
    {
        DB::transaction(function () use ($year, $actor): void {
            $locked = AcademicYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_active) {
                throw AcademicYearGuardException::cannotDeleteActive();
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

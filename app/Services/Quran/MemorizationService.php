<?php

declare(strict_types=1);

namespace App\Services\Quran;

use App\Domain\Quran\AyahRange;
use App\Domain\Quran\RubricScorer;
use App\Models\MemorizationMaterial;
use App\Models\MemorizationRecord;
use App\Models\RubricVersion;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Notifications\MemorizationRecordedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\GuardianNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MemorizationService
{
    public function __construct(
        private readonly RubricScorer $scorer,
        private readonly AuditLogger $audit,
        private readonly GuardianNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(User $teacher, Student $student, RubricVersion $version, array $data): MemorizationRecord
    {
        if ($version->published_at === null) {
            throw ValidationException::withMessages([
                'rubric_version_id' => 'Hanya versi rubrik yang sudah diterbitkan yang dapat dipakai.',
            ]);
        }

        $kind = (string) ($data['kind'] ?? 'quran');
        $type = null;
        $materialId = null;
        $surahFrom = null;
        $ayahFrom = null;
        $surahTo = null;
        $ayahTo = null;
        $summaryLabel = '';

        if ($kind === 'quran') {
            $type = (string) ($data['type'] ?? '');
            if (! in_array($type, ['ziyadah', 'murajaah'], true)) {
                throw ValidationException::withMessages(['type' => 'Pilih jenis setoran ziyadah atau murajaah.']);
            }

            $surahFrom = (int) ($data['surah_from'] ?? 0);
            $ayahFrom = (int) ($data['ayah_from'] ?? 0);
            $surahTo = (int) ($data['surah_to'] ?? 0);
            $ayahTo = (int) ($data['ayah_to'] ?? 0);

            if ($surahFrom < 1 || $surahTo < $surahFrom) {
                throw ValidationException::withMessages(['surah_to' => 'Rentang surah tidak valid.']);
            }

            $surahs = Surah::query()->whereIn('number', [$surahFrom, $surahTo])->get()->keyBy('number');
            $fromEntity = $surahs->get($surahFrom);
            $toEntity = $surahs->get($surahTo);

            if ($fromEntity === null || $toEntity === null) {
                throw ValidationException::withMessages(['surah_from' => 'Surah tidak ditemukan.']);
            }

            try {
                if ($surahFrom === $surahTo) {
                    AyahRange::validate($surahFrom, $ayahFrom, $ayahTo, $fromEntity->ayah_count);
                } else {
                    AyahRange::validate($surahFrom, $ayahFrom, $fromEntity->ayah_count, $fromEntity->ayah_count);
                    AyahRange::validate($surahTo, 1, $ayahTo, $toEntity->ayah_count);
                }
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['ayah_to' => $e->getMessage()]);
            }

            $summaryLabel = $surahFrom === $surahTo
                ? "{$fromEntity->name_latin}: {$ayahFrom}–{$ayahTo}"
                : "{$fromEntity->name_latin}:{$ayahFrom} – {$toEntity->name_latin}:{$ayahTo}";
        } else {
            $materialId = (int) ($data['memorization_material_id'] ?? 0);
            $material = MemorizationMaterial::query()->find($materialId);
            if ($material === null || $material->kind !== $kind) {
                throw ValidationException::withMessages([
                    'memorization_material_id' => 'Pilih materi yang valid dan sesuai jenis hafalan.',
                ]);
            }
            $summaryLabel = $material->title;
        }

        $version->loadMissing('criteria');
        $criteriaPayload = array_values($version->criteria->map(static fn ($c): array => [
            'id' => $c->id,
            'mode' => $c->mode,
            'weight' => $c->weight,
            'max_score' => $c->max_score,
            'deduction_per_error' => $c->deduction_per_error,
            'pass_threshold' => $c->pass_threshold,
        ])->all());

        /** @var array<int|string, mixed> $rawScores */
        $rawScores = is_array($data['scores'] ?? null) ? $data['scores'] : [];
        $normalizedInputs = [];
        foreach ($rawScores as $k => $v) {
            $normalizedInputs[(int) $k] = is_numeric($v) ? (float) $v : -1;
        }

        try {
            $scored = $this->scorer->score($criteriaPayload, $normalizedInputs, $version->pass_threshold);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['scores' => $e->getMessage()]);
        }

        return DB::transaction(function () use (
            $teacher,
            $student,
            $version,
            $kind,
            $type,
            $materialId,
            $surahFrom,
            $ayahFrom,
            $surahTo,
            $ayahTo,
            $normalizedInputs,
            $scored,
            $data,
            $summaryLabel,
        ): MemorizationRecord {
            $record = MemorizationRecord::query()->create([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'rubric_version_id' => $version->id,
                'kind' => $kind,
                'type' => $type,
                'memorization_material_id' => $materialId,
                'surah_from' => $surahFrom,
                'ayah_from' => $ayahFrom,
                'surah_to' => $surahTo,
                'ayah_to' => $ayahTo,
                'scores' => $normalizedInputs,
                'final_score' => $scored['final_score'],
                'predicate' => $scored['predicate'],
                'passed' => $scored['passed'],
                'note' => isset($data['note']) && trim((string) $data['note']) !== '' ? trim((string) $data['note']) : null,
                'recorded_on' => (string) $data['recorded_on'],
            ]);

            $this->audit->record(
                action: 'memorization.recorded',
                subject: $record,
                after: [
                    'student_id' => $student->id,
                    'kind' => $kind,
                    'final_score' => $scored['final_score'],
                    'passed' => $scored['passed'],
                ],
                userId: $teacher->id,
            );

            $this->notifier->notifyStudentGuardians(
                $student,
                new MemorizationRecordedNotification(
                    recordId: $record->id,
                    studentId: $student->id,
                    studentName: $student->name,
                    summaryLabel: $summaryLabel,
                    finalScore: $scored['final_score'],
                    predicate: $scored['predicate'],
                    passed: $scored['passed'],
                ),
            );

            return $record;
        });
    }
}

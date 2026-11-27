<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Domain\Character\CharacterScoreCalculator;
use App\Domain\Character\ComponentMeasure;
use App\Domain\Character\DimensionResult;
use App\Enums\CharacterReviewStatus;
use App\Enums\CharacterSource;
use App\Models\AcademicYear;
use App\Models\CharacterAssessment;
use App\Models\CharacterComponent;
use App\Models\CharacterDimension;
use App\Models\CharacterProfile;
use App\Models\CharacterReview;
use App\Models\Classroom;
use App\Models\HabitItem;
use App\Models\User;
use App\Notifications\CharacterAssessmentApprovedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\GuardianNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur penilaian karakter satu rombel (ADR-029, T-1203):
 * draf (hitung ulang + koreksi wali kelas) → diajukan (kepala unit boleh mengubah capaian) → disetujui.
 * Hitung ulang tidak pernah menimpa capaian akhir, observasi, atau deskripsi yang diubah manusia.
 */
final class CharacterAssessmentService
{
    public function __construct(
        private readonly CharacterInputService $inputs,
        private readonly CharacterScoreCalculator $calculator,
        private readonly AuditLogger $auditLogger,
        private readonly GuardianNotifier $notifier,
    ) {}

    public function compute(Classroom $classroom, AcademicYear $year, User $actor): CharacterReview
    {
        $profile = CharacterProfile::query()->where('unit_id', $classroom->unit_id)->first();

        if ($profile === null) {
            throw ValidationException::withMessages(['review' => 'Penilaian karakter unit ini belum diatur. Minta admin memilih kurikulum di Pengaturan.']);
        }

        $dimensions = CharacterDimension::query()
            ->with('components')
            ->where('unit_id', $classroom->unit_id)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        if ($dimensions->isEmpty()) {
            throw ValidationException::withMessages(['review' => 'Belum ada dimensi aktif untuk unit ini.']);
        }

        return DB::transaction(function () use ($classroom, $year, $actor, $profile, $dimensions): CharacterReview {
            $review = CharacterReview::query()->firstOrCreate(
                ['classroom_id' => $classroom->id, 'academic_year_id' => $year->id],
                ['status' => CharacterReviewStatus::Draft],
            );
            $review = CharacterReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($review->status !== CharacterReviewStatus::Draft) {
                throw ValidationException::withMessages(['review' => 'Penilaian sudah diajukan; minta kepala unit membuka kunci untuk menghitung ulang.']);
            }

            $students = $this->inputs->forClassroom($classroom, $year);
            $items = HabitItem::query()->forUnit($classroom->unit_id)->where('is_active', true)->get(['id', 'name', 'aspect']);
            $existing = $review->assessments()->get()->keyBy(fn (CharacterAssessment $a): string => $a->student_id.':'.$a->character_dimension_id);

            foreach ($students as $student) {
                foreach ($dimensions as $dimension) {
                    /** @var CharacterAssessment|null $assessment */
                    $assessment = $existing->get($student['student_id'].':'.$dimension->id);
                    $measures = array_values($dimension->components
                        ->map(fn (CharacterComponent $c): ComponentMeasure => $this->inputs->measure($c, $student, $items, $assessment?->observation_level))
                        ->all());

                    $result = $this->calculator->calculate($measures, $profile->scale, $profile->coverage_min_pct);
                    $assessment ??= new CharacterAssessment([
                        'character_review_id' => $review->id,
                        'student_id' => $student['student_id'],
                        'character_dimension_id' => $dimension->id,
                    ]);

                    $this->applyResult($assessment, $result, $measures, $profile, $dimension, $student['nickname'] ?? $student['name'], $student);
                    $assessment->save();
                }
            }

            // Siswa yang keluar dari rombel sejak hitungan terakhir tidak ikut dinilai.
            $review->assessments()->whereNotIn('student_id', array_keys($students))->delete();
            $review->forceFill(['computed_at' => now()])->save();

            $this->auditLogger->record(
                action: 'character.computed',
                subject: $review,
                after: ['students' => count($students), 'dimensions' => $dimensions->count()],
                userId: $actor->id,
            );

            return $review;
        });
    }

    /**
     * Koreksi oleh wali kelas (draf) atau kepala unit (diajukan).
     *
     * @param  array{final_level?: string|null, override_reason?: string|null, observation_level?: int|null, description?: string|null}  $data
     */
    public function correct(CharacterAssessment $assessment, array $data, User $actor): CharacterAssessment
    {
        return DB::transaction(function () use ($assessment, $data, $actor): CharacterAssessment {
            $review = CharacterReview::query()->whereKey($assessment->character_review_id)->lockForUpdate()->firstOrFail();

            if ($review->status === CharacterReviewStatus::Approved) {
                throw ValidationException::withMessages(['final_level' => 'Penilaian sudah disetujui; buka kunci terlebih dahulu.']);
            }

            $profile = CharacterProfile::query()->where('unit_id', $review->classroom->unit_id)->firstOrFail();
            $before = $assessment->only(['final_level', 'override_reason', 'observation_level', 'description']);

            if (array_key_exists('observation_level', $data) && $data['observation_level'] !== $assessment->observation_level) {
                $assessment->observation_level = $data['observation_level'];
                $this->recalculateFromInputs($assessment, $profile);
            }

            if (array_key_exists('final_level', $data)) {
                $final = $data['final_level'];
                $codes = array_column($profile->scale, 'code');

                if ($final !== null && ! in_array($final, $codes, true)) {
                    throw ValidationException::withMessages(['final_level' => 'Capaian tidak ada di skala unit ini.']);
                }

                $differs = $final !== null && $final !== $assessment->computed_level;
                $reason = trim((string) ($data['override_reason'] ?? ''));

                if ($differs && $reason === '') {
                    throw ValidationException::withMessages(['override_reason' => 'Tulis alasan karena capaian berbeda dari hasil hitung.']);
                }

                $assessment->final_level = $differs ? $final : null;
                $assessment->override_reason = $differs ? $reason : null;
                $assessment->overridden_by = $differs ? $actor->id : null;
            }

            if (array_key_exists('description', $data) && $data['description'] !== null && trim($data['description']) !== trim((string) $assessment->description)) {
                $assessment->description = trim($data['description']);
                $assessment->description_edited = true;
            } elseif (! $assessment->description_edited) {
                $assessment->description = $this->autoDescription($assessment, $profile);
            }

            $assessment->save();

            $this->auditLogger->record(
                action: 'character.corrected',
                subject: $assessment,
                before: $before,
                after: $assessment->only(['final_level', 'override_reason', 'observation_level', 'description']),
                userId: $actor->id,
            );

            return $assessment;
        });
    }

    public function submit(CharacterReview $review, User $actor): CharacterReview
    {
        return $this->transition($review, CharacterReviewStatus::Draft, CharacterReviewStatus::Submitted, $actor, function (CharacterReview $locked, User $actor): void {
            if ($locked->computed_at === null || ! $locked->assessments()->exists()) {
                throw ValidationException::withMessages(['review' => 'Hitung penilaian terlebih dahulu.']);
            }

            $missing = $locked->assessments()->whereNull('computed_level')->whereNull('final_level')->count();

            if ($missing > 0) {
                throw ValidationException::withMessages(['review' => "{$missing} capaian bertanda data kurang belum ditetapkan."]);
            }

            $locked->forceFill(['submitted_by' => $actor->id, 'submitted_at' => now()]);
        });
    }

    public function approve(CharacterReview $review, User $actor): CharacterReview
    {
        return $this->transition($review, CharacterReviewStatus::Submitted, CharacterReviewStatus::Approved, $actor, function (CharacterReview $locked, User $actor): void {
            $locked->forceFill(['approved_by' => $actor->id, 'approved_at' => now()]);

            // Notifikasi dikirim setelah commit lewat GuardianNotifier (ARCHITECTURE §5).
            $year = $locked->academicYear;
            $semester = $year->name.' '.$year->semester->label();

            $locked->assessments()
                ->with('student')
                ->get()
                ->unique('student_id')
                ->each(fn (CharacterAssessment $a) => $this->notifier->notifyStudentGuardians(
                    $a->student,
                    new CharacterAssessmentApprovedNotification($locked->id, $a->student_id, $a->student->nickname ?? $a->student->name, $semester),
                ));
        });
    }

    public function reopen(CharacterReview $review, string $reason, User $actor): CharacterReview
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Tulis alasan membuka kunci.']);
        }

        return DB::transaction(function () use ($review, $reason, $actor): CharacterReview {
            $locked = CharacterReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CharacterReviewStatus::Draft) {
                throw ValidationException::withMessages(['reason' => 'Penilaian masih berupa draf.']);
            }

            $before = $locked->status->value;
            $locked->forceFill([
                'status' => CharacterReviewStatus::Draft,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'reopened_by' => $actor->id,
                'reopened_at' => now(),
                'reopen_reason' => trim($reason),
            ])->save();

            $this->auditLogger->record(
                action: 'character.reopened',
                subject: $locked,
                before: ['status' => $before],
                after: ['status' => CharacterReviewStatus::Draft->value, 'reason' => trim($reason)],
                userId: $actor->id,
            );

            return $locked;
        });
    }

    /**
     * @param  callable(CharacterReview, User): void  $guard
     */
    private function transition(CharacterReview $review, CharacterReviewStatus $from, CharacterReviewStatus $to, User $actor, callable $guard): CharacterReview
    {
        return DB::transaction(function () use ($review, $from, $to, $actor, $guard): CharacterReview {
            $locked = CharacterReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== $from) {
                throw ValidationException::withMessages(['review' => "Status penilaian sekarang {$locked->status->label()}."]);
            }

            $guard($locked, $actor);
            $locked->status = $to;
            $locked->save();

            $this->auditLogger->record(
                action: 'character.'.$to->value,
                subject: $locked,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
                userId: $actor->id,
            );

            return $locked;
        });
    }

    /**
     * @param  list<ComponentMeasure>  $measures
     * @param  array<string, mixed>  $student
     */
    private function applyResult(CharacterAssessment $assessment, DimensionResult $result, array $measures, CharacterProfile $profile, CharacterDimension $dimension, string $studentName, array $student = []): void
    {
        $assessment->computed_score = $result->score !== null ? number_format($result->score, 2, '.', '') : null;
        $assessment->computed_level = $result->level;

        // Capaian akhir yang kini sama dengan hasil hitung tidak lagi dianggap koreksi.
        if ($assessment->final_level !== null && $assessment->final_level === $result->level) {
            $assessment->final_level = null;
            $assessment->override_reason = null;
            $assessment->overridden_by = null;
        }

        $assessment->inputs = [
            'dimension' => ['id' => $dimension->id, 'name' => $dimension->name, 'templates' => $dimension->description_templates],
            'student_name' => $studentName,
            'period' => ['from' => $student['from'] ?? null, 'to' => $student['to'] ?? null],
            'scale' => $profile->scale,
            'coverage_min_pct' => $profile->coverage_min_pct,
            'measures' => array_map(fn (ComponentMeasure $m): array => [
                'source' => $m->source->value,
                'weight' => $m->weight,
                'params' => $m->params,
                'phrase' => $m->phrase,
                'value' => $m->value,
                'coverage' => $m->coverage,
            ], $measures),
            'components' => $result->components,
            'used_weight' => $result->usedWeight,
            'strongest' => $result->strongest,
            'weakest' => $result->weakest,
        ];

        if (! $assessment->description_edited) {
            $assessment->description = $this->autoDescription($assessment, $profile);
        }
    }

    /**
     * Hitung ulang satu baris dari angka yang tersimpan (mis. setelah observasi guru berubah).
     */
    private function recalculateFromInputs(CharacterAssessment $assessment, CharacterProfile $profile): void
    {
        /** @var list<array{source: string, weight: int, params: array<string, mixed>, phrase: string, value: float|null, coverage: float|null}> $stored */
        $stored = $assessment->inputs['measures'] ?? [];
        $measures = array_map(function (array $m) use ($assessment): ComponentMeasure {
            $source = CharacterSource::from($m['source']);
            $value = $source === CharacterSource::TeacherObservation
                ? ($assessment->observation_level !== null ? (float) $assessment->observation_level : null)
                : $m['value'];

            return new ComponentMeasure($source, $m['weight'], $m['params'], $m['phrase'], $value, $m['coverage']);
        }, $stored);

        /** @var array{id: int, name: string, templates: array<string, string>} $dimensionInfo */
        $dimensionInfo = $assessment->inputs['dimension'];
        $dimension = new CharacterDimension(['name' => $dimensionInfo['name'], 'description_templates' => $dimensionInfo['templates']]);
        $dimension->id = $dimensionInfo['id'];
        $result = $this->calculator->calculate($measures, $profile->scale, $profile->coverage_min_pct);

        /** @var array{from: string|null, to: string|null} $period */
        $period = $assessment->inputs['period'] ?? ['from' => null, 'to' => null];
        $this->applyResult($assessment, $result, $measures, $profile, $dimension, (string) ($assessment->inputs['student_name'] ?? ''), $period);
    }

    private function autoDescription(CharacterAssessment $assessment, CharacterProfile $profile): ?string
    {
        $level = $assessment->effectiveLevel();

        if ($level === null) {
            return null;
        }

        /** @var array<string, string> $templates */
        $templates = $assessment->inputs['dimension']['templates'] ?? [];
        $template = $templates[$level] ?? null;

        if ($template === null) {
            return null;
        }

        return $this->calculator->describe(
            $template,
            (string) ($assessment->inputs['student_name'] ?? ''),
            (string) ($assessment->inputs['dimension']['name'] ?? ''),
            $assessment->inputs['strongest'] ?? null,
            $assessment->inputs['weakest'] ?? null,
        );
    }
}

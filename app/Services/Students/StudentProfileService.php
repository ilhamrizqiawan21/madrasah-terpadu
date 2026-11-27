<?php

declare(strict_types=1);

namespace App\Services\Students;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceStatus;
use App\Enums\BehaviorRecordStatus;
use App\Enums\CharacterReviewStatus;
use App\Enums\ExamResultStatus;
use App\Enums\Feature;
use App\Enums\PointCategory;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\BehaviorRecord;
use App\Models\CharacterReview;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\Guardian;
use App\Models\HabitLog;
use App\Models\Student;
use App\Services\Character\CharacterReportService;
use App\Services\Features\FeatureFlags;
use App\Services\Quran\MemorizationProgressService;
use Carbon\CarbonImmutable;

/**
 * Profil siswa terpadu untuk staf (mockup 03). Hanya-baca; bagian modul yang dimatikan bernilai null.
 */
final class StudentProfileService
{
    public function __construct(
        private readonly FeatureFlags $features,
        private readonly MemorizationProgressService $memorization,
        private readonly CharacterReportService $character,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Student $student): array
    {
        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;
        $now = CarbonImmutable::now($tz);
        $year = AcademicYear::query()->where('is_active', true)->first();
        $student->load(['unit:id,code,name', 'guardians', 'enrollments.classroom:id,name', 'enrollments.academicYear:id,name,semester,is_active']);

        $current = $student->enrollments->firstWhere('academic_year_id', $year?->id);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nickname' => $student->nickname,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'gender' => $student->gender->label(),
                'birth' => trim(($student->birth_place ?? '').($student->birth_date !== null ? ', '.$student->birth_date->translatedFormat('j F Y') : ''), ', '),
                'address' => $student->address,
                'status' => $student->status->value,
                'status_label' => $student->status->label(),
                'unit' => $student->unit->code,
                'unit_name' => $student->unit->name,
                'classroom' => $current?->classroom->name,
                'photo_url' => $student->photo_path !== null ? route('admin.students.photo', $student) : null,
            ],
            'guardians' => array_values($student->guardians->map(fn (Guardian $g): array => [
                'name' => $g->name,
                'phone' => $g->phone,
                'relation' => $g->relation->label(),
                'is_primary' => (bool) $g->getRelation('link')->getAttribute('is_primary'),
            ])->all()),
            'enrollments' => array_values($student->enrollments
                ->sortByDesc('academic_year_id')
                ->map(fn (Enrollment $e): array => [
                    'year' => $e->academicYear->name.' '.$e->academicYear->semester->label(),
                    'classroom' => $e->classroom->name,
                    'is_active' => $e->academicYear->is_active,
                ])->all()),
            'attendance' => $this->features->enabled(Feature::Attendance) ? $this->attendance($student, $now) : null,
            'behavior' => $this->features->enabled(Feature::Behavior) && $year !== null ? $this->behavior($student, $year) : null,
            'habits' => $this->features->enabled(Feature::Habits) ? $this->habits($student, $now) : null,
            'quran' => $this->features->enabled(Feature::Quran) ? $this->memorization->progressForStudent($student, $year) : null,
            'character' => $this->features->enabled(Feature::Attitude) ? $this->characterSection($student) : null,
            'exams' => $this->features->enabled(Feature::Cbt) ? $this->exams($student) : null,
        ];
    }

    /**
     * @return array{month: string, counts: array<string, int>, days: list<array{date: string, status: string, label: string, in: string|null, out: string|null}>}
     */
    private function attendance(Student $student, CarbonImmutable $now): array
    {
        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;
        $days = AttendanceDay::query()
            ->where('student_id', $student->id)
            ->whereDate('school_date', '>=', $now->startOfMonth()->toDateString())
            ->whereDate('school_date', '<=', $now->toDateString())
            ->orderBy('school_date')
            ->get();

        $counts = array_fill_keys(array_map(fn (AttendanceStatus $s): string => $s->value, AttendanceStatus::cases()), 0);

        foreach ($days as $day) {
            $counts[$day->status->value]++;
        }

        return [
            'month' => $now->translatedFormat('F Y'),
            'counts' => $counts,
            'days' => array_values($days->map(fn (AttendanceDay $d): array => [
                'date' => $d->school_date->toDateString(),
                'status' => $d->status->value,
                'label' => $d->status->label(),
                'in' => $d->checked_in_at?->setTimezone($tz)->format('H.i'),
                'out' => $d->checked_out_at?->setTimezone($tz)->format('H.i'),
            ])->all()),
        ];
    }

    /**
     * Poin dihitung dari catatan aktif tahun ini (bukan saldo; penukaran reward tidak mengurangi).
     *
     * @return array{merit: int, violation: int, records: list<array{id: int, date: string, rule: string, category: string, category_label: string, points: int, voided: bool}>}
     */
    private function behavior(Student $student, AcademicYear $year): array
    {
        $records = BehaviorRecord::query()
            ->with('pointRule:id,name')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $active = $records->where('status', BehaviorRecordStatus::Active);

        return [
            'merit' => (int) $active->filter(fn (BehaviorRecord $r): bool => $r->category !== PointCategory::Violation)->sum('points'),
            'violation' => (int) $active->filter(fn (BehaviorRecord $r): bool => $r->category === PointCategory::Violation)->sum('points'),
            'records' => array_values($records->take(15)->map(fn (BehaviorRecord $r): array => [
                'id' => $r->id,
                'date' => $r->occurred_on->toDateString(),
                'rule' => $r->pointRule->name,
                'category' => $r->category->value,
                'category_label' => $r->category->label(),
                'points' => $r->points,
                'voided' => $r->status === BehaviorRecordStatus::Voided,
            ])->all()),
        ];
    }

    /**
     * @return array{verified: int, pending: int, rejected: int, days_logged: int}
     */
    private function habits(Student $student, CarbonImmutable $now): array
    {
        $logs = HabitLog::query()
            ->where('student_id', $student->id)
            ->whereDate('log_date', '>=', $now->startOfMonth()->toDateString())
            ->where('done', true)
            ->get(['id', 'log_date', 'verification']);

        return [
            'verified' => $logs->where('verification', 'verified')->count(),
            'pending' => $logs->where('verification', 'pending')->count(),
            'rejected' => $logs->where('verification', 'rejected')->count(),
            'days_logged' => $logs->map(fn (HabitLog $l): string => $l->log_date->toDateString())->unique()->count(),
        ];
    }

    /**
     * @return array{status: string, status_label: string, semester: string, dimensions: list<array{name: string, level: string|null, level_label: string, description: string|null}>}|null
     */
    private function characterSection(Student $student): ?array
    {
        $review = CharacterReview::query()
            ->with('academicYear')
            ->whereHas('assessments', fn ($q) => $q->where('student_id', $student->id))
            ->orderByDesc('academic_year_id')
            ->first();

        if ($review === null) {
            return null;
        }

        return [
            'status' => $review->status->value,
            'status_label' => $review->status === CharacterReviewStatus::Approved ? 'Disetujui' : $review->status->label(),
            'semester' => $review->academicYear->name.' '.$review->academicYear->semester->label(),
            'dimensions' => $this->character->rows($review, $student->id)[0]['dimensions'] ?? [],
        ];
    }

    /**
     * @return list<array{title: string, subject: string, score: int, date: string}>
     */
    private function exams(Student $student): array
    {
        return array_values(ExamResult::query()
            ->with('session.exam.subject')
            ->where('status', ExamResultStatus::Final->value)
            ->whereHas('session', fn ($q) => $q->where('student_id', $student->id))
            ->orderByDesc('computed_at')
            ->limit(10)
            ->get()
            ->map(fn (ExamResult $r): array => [
                'title' => $r->session->exam->title,
                'subject' => $r->session->exam->subject->name,
                'score' => $r->score,
                'date' => $r->computed_at->toIso8601String(),
            ])->all());
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Enums\ExamSessionStatus;
use App\Enums\StudentStatus;
use App\Jobs\ScoreExamSession;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Layanan monitor pengawas ujian CBT (T-1509, ADR-020).
 */
final class ExamMonitorService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Ambil ringkasan dan daftar peserta ujian dengan kueri agregat (tanpa N+1).
     *
     * @return array{
     *     total_questions: int,
     *     summary: array{not_started: int, in_progress: int, locked: int, finished: int},
     *     students: list<array{
     *         student_id: int,
     *         nis: string,
     *         name: string,
     *         classroom: string,
     *         session_id: int|null,
     *         status: string,
     *         status_label: string,
     *         answered_count: int,
     *         violations_count: int,
     *         device_type: string|null,
     *         extra_minutes: int,
     *         last_seen_at: string|null
     *     }>
     * }
     */
    public function snapshot(Exam $exam): array
    {
        $totalQuestions = $exam->questions()->count();
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $students = $activeYearId !== null && $exam->classroom_ids !== []
            ? Student::query()
                ->with(['enrollments' => fn ($q) => $q
                    ->where('academic_year_id', $activeYearId)
                    ->whereIn('classroom_id', $exam->classroom_ids)
                    ->with('classroom:id,name')])
                ->where('status', StudentStatus::Active)
                ->whereHas('enrollments', fn ($q) => $q
                    ->where('academic_year_id', $activeYearId)
                    ->whereIn('classroom_id', $exam->classroom_ids))
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'nis', 'name'])
            : collect();

        $sessions = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->withCount([
                'answers',
                'violations as counted_violations_count' => fn ($q) => $q->where('counted', true),
            ])
            ->get()
            ->keyBy('student_id');

        $summary = [
            'not_started' => 0,
            'in_progress' => 0,
            'locked' => 0,
            'finished' => 0,
        ];

        $rows = [];
        foreach ($students as $student) {
            /** @var ExamSession|null $session */
            $session = $sessions->get($student->id);
            $classroomName = $student->enrollments->first()?->classroom->name ?? '—';

            if ($session === null) {
                $summary['not_started']++;
                $status = 'not_started';
                $statusLabel = 'Belum mulai';
            } elseif ($session->status === ExamSessionStatus::InProgress) {
                $summary['in_progress']++;
                $status = $session->status->value;
                $statusLabel = $session->status->label();
            } elseif ($session->status === ExamSessionStatus::Locked) {
                $summary['locked']++;
                $status = $session->status->value;
                $statusLabel = $session->status->label();
            } else {
                $summary['finished']++;
                $status = $session->status->value;
                $statusLabel = $session->status->label();
            }

            $rows[] = [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'classroom' => $classroomName,
                'session_id' => $session?->id,
                'status' => $status,
                'status_label' => $statusLabel,
                'answered_count' => $session !== null ? (int) $session->answers_count : 0,
                'violations_count' => $session !== null ? (int) $session->counted_violations_count : 0,
                'device_type' => $session?->device_type,
                'extra_minutes' => $session !== null ? $session->extra_minutes : 0,
                'last_seen_at' => $session?->last_seen_at?->toIso8601String(),
            ];
        }

        return [
            'total_questions' => $totalQuestions,
            'summary' => $summary,
            'students' => $rows,
        ];
    }

    public function unlock(ExamSession $session, User $actor): ExamSession
    {
        return DB::transaction(function () use ($session, $actor): ExamSession {
            $before = ['status' => $session->status->value];

            if ($session->status === ExamSessionStatus::Locked) {
                $session->forceFill(['status' => ExamSessionStatus::InProgress])->save();
            }

            $this->auditLogger->record(
                action: 'exam_session_unlocked',
                subject: $session,
                before: $before,
                after: ['status' => $session->status->value],
                userId: $actor->id,
            );

            return $session;
        });
    }

    public function addExtraTime(ExamSession $session, int $minutes, User $actor): ExamSession
    {
        return DB::transaction(function () use ($session, $minutes, $actor): ExamSession {
            $before = ['extra_minutes' => $session->extra_minutes];

            $session->forceFill(['extra_minutes' => $session->extra_minutes + $minutes])->save();

            $this->auditLogger->record(
                action: 'exam_session_extra_time',
                subject: $session,
                before: $before,
                after: ['extra_minutes' => $session->extra_minutes, 'added' => $minutes],
                userId: $actor->id,
            );

            return $session;
        });
    }

    public function forceClose(ExamSession $session, User $actor): ExamSession
    {
        return DB::transaction(function () use ($session, $actor): ExamSession {
            $before = ['status' => $session->status->value];

            $session->forceFill([
                'status' => ExamSessionStatus::ForceClosed,
                'last_seen_at' => CarbonImmutable::now(),
            ])->save();

            $this->auditLogger->record(
                action: 'exam_session_force_closed',
                subject: $session,
                before: $before,
                after: ['status' => ExamSessionStatus::ForceClosed->value],
                userId: $actor->id,
            );

            ScoreExamSession::dispatch($session->id)->afterCommit();

            return $session;
        });
    }
}

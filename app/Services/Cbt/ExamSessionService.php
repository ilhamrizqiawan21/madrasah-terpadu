<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Domain\Exam\ExamEligibilityResolver;
use App\Domain\Exam\SessionClock;
use App\Domain\Exam\ShuffleOrder;
use App\Enums\ExamSessionStatus;
use App\Enums\ExamStatus;
use App\Exceptions\Cbt\DeviceTakeoverException;
use App\Exceptions\Cbt\ExamAccessException;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pengelola sesi ujian siswa, take-over device_token, dan sinkron jawaban batch (T-1505, ARCHITECTURE §8b).
 */
final class ExamSessionService
{
    public function __construct(
        private readonly ExamEligibilityResolver $eligibilityResolver,
    ) {}

    public function startOrResume(
        Exam $exam,
        Student $student,
        string $deviceType,
        ?string $accessToken,
    ): ExamSession {
        if (! in_array($exam->status, [ExamStatus::Scheduled, ExamStatus::Running], true)) {
            throw new ExamAccessException('exam', 'Ujian belum dibuka atau sudah selesai.');
        }

        $now = CarbonImmutable::now();
        if ($now->lessThan($exam->opens_at) || $now->greaterThan($exam->closes_at)) {
            throw new ExamAccessException('exam', 'Ujian berada di luar jendela waktu pelaksanaan.');
        }

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $enrolled = $activeYearId !== null && $student->enrollments()
            ->where('academic_year_id', $activeYearId)
            ->whereIn('classroom_id', $exam->classroom_ids)
            ->exists();

        if (! $enrolled) {
            throw new ExamAccessException('exam', 'Ujian ini tidak dijadwalkan untuk rombel Anda.');
        }

        if (! in_array($deviceType, $exam->device_modes, true)) {
            throw new ExamAccessException('device_type', 'Mode perangkat ini tidak diizinkan untuk ujian ini.');
        }

        if ($exam->access_token !== null) {
            $normalized = mb_strtoupper(trim((string) $accessToken));
            if ($normalized !== $exam->access_token) {
                throw new ExamAccessException('access_token', 'Token ujian tidak sesuai.');
            }
        }

        if (! $this->eligibilityResolver->isEligible($exam, $student)) {
            throw new ExamAccessException('exam', 'Hubungi admin sekolah.');
        }

        return DB::transaction(function () use ($exam, $student, $deviceType, $now): ExamSession {
            $existing = ExamSession::query()
                ->where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ExamSession) {
                if ($existing->status === ExamSessionStatus::Locked) {
                    throw new ExamAccessException('exam', 'Sesi ujian Anda sedang terkunci. Hubungi pengawas.');
                }

                if ($existing->status->isFinished()) {
                    throw new ExamAccessException('exam', 'Anda sudah menyelesaikan ujian ini.');
                }

                $newToken = bin2hex(random_bytes(32));
                $existing->forceFill([
                    'device_type' => $deviceType,
                    'device_token' => $newToken,
                    'last_seen_at' => $now,
                    'takeover_count' => $existing->takeover_count + 1,
                ])->save();

                return $existing;
            }

            if ($exam->locked_at === null) {
                $exam->forceFill(['locked_at' => $now])->save();
            }

            return ExamSession::query()->create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'started_at' => $now,
                'deadline_at' => SessionClock::computeDeadline($now, $exam->duration_minutes, $exam->closes_at),
                'extra_minutes' => 0,
                'status' => ExamSessionStatus::InProgress,
                'device_type' => $deviceType,
                'device_token' => bin2hex(random_bytes(32)),
                'shuffle_seed' => random_int(1, 2147483647),
                'last_seen_at' => $now,
                'takeover_count' => 0,
            ]);
        });
    }

    /**
     * @param  list<array{exam_question_id: int, answer: array<string, mixed>|null, is_flagged: bool, client_ts: string}>  $answers
     * @return array{accepted: int, server_time: string}
     */
    public function syncAnswers(ExamSession $session, string $deviceToken, array $answers): array
    {
        if (! hash_equals($session->device_token, $deviceToken)) {
            throw new DeviceTakeoverException;
        }

        if ($session->status !== ExamSessionStatus::InProgress) {
            throw new ExamAccessException('exam', 'Sesi ujian sudah tidak aktif.');
        }

        $now = CarbonImmutable::now();
        if (! SessionClock::isWithinGrace($now, $session->deadline_at, $session->extra_minutes)) {
            throw new ExamAccessException('exam', 'Batas waktu pengiriman jawaban telah berakhir.');
        }

        $validQuestionIds = $session->exam->questions()->pluck('id')->all();
        $accepted = 0;

        DB::transaction(function () use ($session, $answers, $validQuestionIds, $now, &$accepted): void {
            foreach ($answers as $item) {
                if (! in_array($item['exam_question_id'], $validQuestionIds, true)) {
                    continue;
                }

                ExamAnswer::query()->updateOrCreate(
                    [
                        'exam_session_id' => $session->id,
                        'exam_question_id' => $item['exam_question_id'],
                    ],
                    [
                        'answer' => $item['answer'],
                        'is_flagged' => $item['is_flagged'],
                        'client_ts' => CarbonImmutable::parse($item['client_ts'])->utc(),
                        'received_at' => $now,
                    ],
                );

                $accepted++;
            }

            $session->forceFill(['last_seen_at' => $now])->save();
        });

        return [
            'accepted' => $accepted,
            'server_time' => $now->toIso8601String(),
        ];
    }

    public function submit(ExamSession $session, string $deviceToken): ExamSession
    {
        if (! hash_equals($session->device_token, $deviceToken)) {
            throw new DeviceTakeoverException;
        }

        if ($session->status !== ExamSessionStatus::InProgress) {
            return $session;
        }

        return DB::transaction(function () use ($session): ExamSession {
            $session->forceFill([
                'status' => ExamSessionStatus::Submitted,
                'last_seen_at' => CarbonImmutable::now(),
            ])->save();

            return $session;
        });
    }

    /**
     * Susun paket soal deterministik untuk sesi siswa berdasarkan `shuffle_seed`.
     *
     * @return list<ExamQuestion>
     */
    public function orderedQuestions(ExamSession $session): array
    {
        $questions = array_values($session->exam->questions()->with('options')->get()->all());

        if ($session->exam->shuffle_questions) {
            $questions = ShuffleOrder::apply($questions, $session->shuffle_seed);
        }

        if ($session->exam->shuffle_options) {
            foreach ($questions as $question) {
                $shuffledOptions = ShuffleOrder::apply(
                    array_values($question->options->all()),
                    $session->shuffle_seed + $question->id,
                );
                $question->setRelation('options', $question->newCollection($shuffledOptions));
            }
        }

        return $questions;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Behavior;

use App\Enums\BehaviorRecordStatus;
use App\Enums\PointAccount;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\User;
use App\Notifications\BehaviorRecordedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\GuardianNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BehaviorRecordService
{
    public function __construct(
        private readonly PointLedgerService $ledger,
        private readonly DisciplineFollowupService $followups,
        private readonly AuditLogger $audit,
        private readonly GuardianNotifier $notifier,
    ) {}

    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, BehaviorRecord>
     */
    public function recordMany(
        User $actor,
        array $studentIds,
        PointRule $rule,
        string $occurredOn,
        ?string $note = null,
        ?UploadedFile $evidence = null,
        ?AcademicYear $academicYear = null,
    ): Collection {
        if (! $rule->is_active) {
            throw ValidationException::withMessages([
                'point_rule_id' => 'Aturan poin sedang nonaktif.',
            ]);
        }

        $uniqueStudentIds = array_values(array_unique(array_map('intval', $studentIds)));
        if ($uniqueStudentIds === []) {
            throw ValidationException::withMessages([
                'student_ids' => 'Pilih minimal satu siswa.',
            ]);
        }

        $year = $academicYear ?? AcademicYear::query()->where('is_active', true)->first();
        if ($year === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Belum ada tahun ajaran aktif.',
            ]);
        }

        $evidencePath = $evidence !== null ? $this->storeEvidence($evidence) : null;
        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $dateLabel = CarbonImmutable::parse($occurredOn, is_string($timezone) ? $timezone : 'Asia/Makassar')
            ->format('d/m/Y');

        return DB::transaction(function () use (
            $actor,
            $uniqueStudentIds,
            $rule,
            $year,
            $occurredOn,
            $note,
            $evidencePath,
            $dateLabel,
        ): Collection {
            $students = Student::query()
                ->whereIn('id', $uniqueStudentIds)
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            $records = new Collection;
            $account = PointAccount::from($rule->category->account());

            foreach ($uniqueStudentIds as $studentId) {
                $student = $students->get($studentId);
                if ($student === null) {
                    continue;
                }

                $record = BehaviorRecord::query()->create([
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'point_rule_id' => $rule->id,
                    'category' => $rule->category->value,
                    'points' => $rule->points,
                    'occurred_on' => $occurredOn,
                    'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                    'evidence_path' => $evidencePath,
                    'recorded_by' => $actor->id,
                    'status' => BehaviorRecordStatus::Active->value,
                ]);

                $entry = $this->ledger->post(
                    student: $student,
                    academicYear: $year,
                    account: $account,
                    delta: $rule->points,
                    source: $record,
                    idempotencyKey: "behavior-record:{$record->id}",
                );

                if ($account === PointAccount::Violation) {
                    $this->followups->evaluateForStudent(
                        student: $student,
                        academicYear: $year,
                        violationBalance: $entry->balance_after,
                        actor: $actor,
                    );
                }

                $this->audit->record(
                    action: 'behavior.recorded',
                    subject: $record,
                    after: [
                        'student_id' => $student->id,
                        'point_rule_id' => $rule->id,
                        'category' => $rule->category->value,
                        'points' => $rule->points,
                        'occurred_on' => $occurredOn,
                    ],
                    userId: $actor->id,
                );

                $this->notifier->notifyStudentGuardians(
                    $student,
                    new BehaviorRecordedNotification(
                        recordId: $record->id,
                        studentId: $student->id,
                        studentName: $student->name,
                        categoryValue: $rule->category->value,
                        ruleName: $rule->name,
                        points: $rule->points,
                        dateLabel: $dateLabel,
                    ),
                );

                $records->push($record);
            }

            return $records;
        });
    }

    public function void(User $actor, BehaviorRecord $record, string $reason): BehaviorRecord
    {
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'void_reason' => 'Alasan pembatalan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $record, $trimmedReason): BehaviorRecord {
            /** @var BehaviorRecord $locked */
            $locked = BehaviorRecord::query()
                ->with(['student', 'academicYear'])
                ->lockForUpdate()
                ->findOrFail($record->id);

            if ($locked->status === BehaviorRecordStatus::Voided) {
                throw ValidationException::withMessages([
                    'void_reason' => 'Catatan perilaku ini sudah dibatalkan.',
                ]);
            }

            $before = ['status' => $locked->status->value];
            $locked->update([
                'status' => BehaviorRecordStatus::Voided->value,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $trimmedReason,
            ]);

            $account = PointAccount::from($locked->category->account());
            $this->ledger->post(
                student: $locked->student,
                academicYear: $locked->academicYear,
                account: $account,
                delta: -$locked->points,
                source: $locked,
                idempotencyKey: "behavior-void:{$locked->id}",
            );

            $this->audit->record(
                action: 'behavior.voided',
                subject: $locked,
                before: $before,
                after: [
                    'status' => BehaviorRecordStatus::Voided->value,
                    'void_reason' => $trimmedReason,
                ],
                userId: $actor->id,
            );

            return $locked;
        });
    }

    private function storeEvidence(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$ext;
        $dir = 'behavior/evidence';
        Storage::disk('local')->putFileAs($dir, $file, $filename);

        return $dir.'/'.$filename;
    }
}

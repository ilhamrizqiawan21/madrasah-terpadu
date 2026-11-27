<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Enums\ExamQuestionType;
use App\Jobs\ScoreExamSession;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Satu-satunya penulis bank soal CBT (T-1504).
 */
final class ExamQuestionService
{
    public const DISK = 'local';

    public const IMAGE_DIR = 'cbt/questions';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{type: string, body: string, is_rtl: bool, sort_order?: int, options?: list<array{label: string, body: string}>, answer_key?: array<string, mixed>|null}  $data
     */
    public function create(Exam $exam, array $data, ?UploadedFile $image, User $actor): ExamQuestion
    {
        $imagePath = $image !== null ? $this->storeImage($image) : null;

        return DB::transaction(function () use ($exam, $data, $imagePath, $actor): ExamQuestion {
            $nextOrder = $data['sort_order'] ?? (((int) $exam->questions()->max('sort_order')) + 1);

            $question = ExamQuestion::query()->create([
                'exam_id' => $exam->id,
                'type' => $data['type'],
                'body' => $data['body'],
                'is_rtl' => $data['is_rtl'],
                'image_path' => $imagePath,
                'sort_order' => $nextOrder,
                'answer_key' => $data['answer_key'] ?? null,
            ]);

            $this->syncOptions($question, $data['options'] ?? []);

            $this->auditLogger->record(
                action: 'created',
                subject: $question,
                after: ['exam_id' => $exam->id, 'type' => $question->type->value],
                userId: $actor->id,
            );

            return $question->load('options');
        });
    }

    /**
     * @param  array{type: string, body: string, is_rtl: bool, sort_order?: int, options?: list<array{label: string, body: string}>, answer_key?: array<string, mixed>|null}  $data
     */
    public function update(
        Exam $exam,
        ExamQuestion $question,
        array $data,
        ?UploadedFile $image,
        bool $removeImage,
        User $actor,
    ): ExamQuestion {
        $oldImage = $question->image_path;
        $newImage = $image !== null ? $this->storeImage($image) : ($removeImage ? null : $oldImage);

        $updated = DB::transaction(function () use ($exam, $question, $data, $newImage, $actor): ExamQuestion {
            $before = ['type' => $question->type->value, 'body' => $question->body];

            $question->fill([
                'type' => $data['type'],
                'body' => $data['body'],
                'is_rtl' => $data['is_rtl'],
                'image_path' => $newImage,
                'sort_order' => $data['sort_order'] ?? $question->sort_order,
                'answer_key' => $data['answer_key'] ?? null,
            ])->save();

            $this->syncOptions($question, $data['options'] ?? []);

            $this->auditLogger->record(
                action: 'updated',
                subject: $question,
                before: $before,
                after: ['exam_id' => $exam->id, 'type' => $question->type->value],
                userId: $actor->id,
            );

            return $question->load('options');
        });

        if ($oldImage !== null && $oldImage !== $newImage) {
            Storage::disk(self::DISK)->delete($oldImage);
        }

        return $updated;
    }

    public function delete(Exam $exam, ExamQuestion $question, User $actor): void
    {
        $oldImage = $question->image_path;

        DB::transaction(function () use ($exam, $question, $actor): void {
            $this->auditLogger->record(
                action: 'deleted',
                subject: $question,
                before: ['exam_id' => $exam->id, 'type' => $question->type->value],
                userId: $actor->id,
            );

            $question->delete();
        });

        if ($oldImage !== null) {
            Storage::disk(self::DISK)->delete($oldImage);
        }
    }

    /**
     * Koreksi kunci jawaban setelah ujian dimulai (hanya admin, wajib alasan, diaudit).
     *
     * @param  array<string, mixed>  $answerKey
     */
    public function correctAnswerKey(
        ExamQuestion $question,
        array $answerKey,
        string $reason,
        User $actor,
    ): ExamQuestion {
        return DB::transaction(function () use ($question, $answerKey, $reason, $actor): ExamQuestion {
            $before = ['answer_key' => $question->answer_key];

            $question->forceFill(['answer_key' => $answerKey])->save();

            $this->auditLogger->record(
                action: 'answer_key_corrected',
                subject: $question,
                before: $before,
                after: ['answer_key' => $answerKey, 'reason' => trim($reason)],
                userId: $actor->id,
            );

            $scoredSessionIds = ExamSession::query()
                ->where('exam_id', $question->exam_id)
                ->whereHas('result')
                ->pluck('id');

            foreach ($scoredSessionIds as $sessionId) {
                ScoreExamSession::dispatch((int) $sessionId)->afterCommit();
            }

            return $question;
        });
    }

    /**
     * Impor soal dari berkas Excel (.xlsx).
     * Kolom: type, body, is_rtl, option_a, option_b, option_c, option_d, answer_key.
     *
     * @return array{created: int, failed: list<array{row: int, error: string}>}
     */
    public function importFromExcel(Exam $exam, UploadedFile $file, User $actor): array
    {
        $reader = new Reader;
        $reader->open($file->getRealPath());

        $created = 0;
        $failed = [];
        $headers = [];
        $rowNumber = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $cells = $row->toArray();

                    if ($rowNumber === 1) {
                        $headers = array_map(
                            static fn (mixed $v): string => mb_strtolower(trim(is_scalar($v) ? (string) $v : '')),
                            $cells,
                        );

                        continue;
                    }

                    $mapped = [];
                    foreach ($headers as $idx => $key) {
                        if ($key !== '') {
                            $raw = $cells[$idx] ?? '';
                            $mapped[$key] = trim(is_scalar($raw) ? (string) $raw : '');
                        }
                    }

                    $typeValue = $mapped['type'] ?? '';
                    $body = $mapped['body'] ?? '';

                    if ($body === '') {
                        continue;
                    }

                    $type = ExamQuestionType::tryFrom($typeValue);
                    if ($type === null) {
                        $failed[] = ['row' => $rowNumber, 'error' => "Jenis soal '{$typeValue}' tidak valid."];

                        continue;
                    }

                    $options = [];
                    foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $col => $label) {
                        $optBody = $mapped["option_{$col}"] ?? '';
                        if ($optBody !== '') {
                            $options[] = ['label' => $label, 'body' => $optBody];
                        }
                    }

                    $rawKey = $mapped['answer_key'] ?? '';
                    $answerKey = match ($type) {
                        ExamQuestionType::Mcq => ['correct' => mb_strtoupper($rawKey)],
                        ExamQuestionType::Matching => ['pairs' => json_decode($rawKey, true) ?: []],
                        ExamQuestionType::EssayPaper => null,
                    };

                    $this->create($exam, [
                        'type' => $type->value,
                        'body' => $body,
                        'is_rtl' => in_array($mapped['is_rtl'] ?? '0', ['1', 'true', 'ya'], true),
                        'options' => $options,
                        'answer_key' => $answerKey,
                    ], null, $actor);

                    $created++;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return ['created' => $created, 'failed' => $failed];
    }

    /**
     * @param  list<array{label: string, body: string}>  $options
     */
    private function syncOptions(ExamQuestion $question, array $options): void
    {
        $question->options()->delete();

        foreach ($options as $index => $option) {
            $question->options()->create([
                'label' => trim($option['label']),
                'body' => trim($option['body']),
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function storeImage(UploadedFile $image): string
    {
        $ext = strtolower($image->getClientOriginalExtension() ?: 'png');
        $filename = Str::uuid()->toString().'.'.$ext;
        $path = self::IMAGE_DIR.'/'.$filename;

        Storage::disk(self::DISK)->putFileAs(self::IMAGE_DIR, $image, $filename);

        return $path;
    }
}

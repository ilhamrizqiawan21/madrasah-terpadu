<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ExamDeviceMode;
use App\Enums\Permission;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use App\Services\Cbt\ExamService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->can(Permission::CbtManage->value) && ! $user->can(Permission::CbtTeach->value)) {
            return false;
        }

        /** @var Exam|null $exam */
        $exam = $this->route('exam');
        if ($exam !== null && $user->cannot('update', $exam)) {
            return false;
        }

        $subjectId = $this->integer('subject_id');
        /** @var list<int> $classroomIds */
        $classroomIds = array_values(array_map('intval', (array) $this->input('classroom_ids', [])));

        if ($subjectId > 0 && $classroomIds !== []) {
            return app(ExamService::class)->canTeachTarget($user, $subjectId, $classroomIds);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'exam_period_id' => ['nullable', 'integer'],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:150'],
            'grade' => ['nullable', 'string', 'max:10'],
            'classroom_ids' => [
                'required',
                'array',
                'min:1',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $subject = Subject::query()->find($this->integer('subject_id'));
                    if (! $subject instanceof Subject || ! is_array($value)) {
                        return;
                    }

                    $ids = array_values(array_unique(array_map('intval', $value)));
                    $validCount = Classroom::query()
                        ->whereKey($ids)
                        ->where('unit_id', $subject->unit_id)
                        ->where('is_active', true)
                        ->count();

                    if ($validCount !== count($ids)) {
                        $fail('Semua rombel terpilih harus aktif dan berasal dari unit yang sama dengan mapel.');
                    }
                },
            ],
            'classroom_ids.*' => ['integer', 'distinct'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:300'],
            'shuffle_questions' => ['required', 'boolean'],
            'shuffle_options' => ['required', 'boolean'],
            'weights' => ['required', 'array'],
            'weights.mcq' => ['required', 'integer', 'min:0', 'max:100'],
            'weights.matching' => ['required', 'integer', 'min:0', 'max:100'],
            'weights.essay_paper' => ['required', 'integer', 'min:0', 'max:100'],
            'access_token' => ['nullable', 'string', 'max:20'],
            'violation_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'device_modes' => ['required', 'array', 'min:1'],
            'device_modes.*' => ['string', Rule::in(array_map(fn (ExamDeviceMode $m): string => $m->value, ExamDeviceMode::cases()))],
            'show_result_to_guardian' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject_id' => 'mata pelajaran',
            'title' => 'judul ujian',
            'classroom_ids' => 'rombel',
            'opens_at' => 'waktu mulai',
            'closes_at' => 'waktu selesai',
            'duration_minutes' => 'durasi ujian',
            'device_modes' => 'mode perangkat',
            'violation_limit' => 'batas pelanggaran',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function examData(): array
    {
        $validated = $this->validated();
        $tz = config('app.timezone', 'Asia/Makassar');

        return [
            'exam_period_id' => $validated['exam_period_id'] ?? null,
            'subject_id' => (int) $validated['subject_id'],
            'title' => trim((string) $validated['title']),
            'grade' => isset($validated['grade']) && $validated['grade'] !== '' ? trim((string) $validated['grade']) : null,
            'classroom_ids' => array_values(array_unique(array_map('intval', (array) $validated['classroom_ids']))),
            'opens_at' => CarbonImmutable::parse((string) $validated['opens_at'], $tz)->utc(),
            'closes_at' => CarbonImmutable::parse((string) $validated['closes_at'], $tz)->utc(),
            'duration_minutes' => (int) $validated['duration_minutes'],
            'shuffle_questions' => (bool) $validated['shuffle_questions'],
            'shuffle_options' => (bool) $validated['shuffle_options'],
            'weights' => [
                'mcq' => (int) $validated['weights']['mcq'],
                'matching' => (int) $validated['weights']['matching'],
                'essay_paper' => (int) $validated['weights']['essay_paper'],
            ],
            'access_token' => isset($validated['access_token']) && trim((string) $validated['access_token']) !== ''
                ? mb_strtoupper(trim((string) $validated['access_token']))
                : null,
            'violation_limit' => (int) $validated['violation_limit'],
            'device_modes' => array_values(array_unique(array_map('strval', (array) $validated['device_modes']))),
            'show_result_to_guardian' => (bool) $validated['show_result_to_guardian'],
        ];
    }
}

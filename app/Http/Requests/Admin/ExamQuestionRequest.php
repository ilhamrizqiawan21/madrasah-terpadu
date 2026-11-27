<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ExamQuestionType;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');
        /** @var Exam|null $exam */
        $exam = $this->route('exam');

        if (! $user instanceof User || ! $exam instanceof Exam) {
            return false;
        }

        if ($user->cannot('update', $exam)) {
            abort(404);
        }

        return ! $exam->isLocked();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_map(fn (ExamQuestionType $t): string => $t->value, ExamQuestionType::cases()))],
            'body' => ['required', 'string', 'max:10000'],
            'is_rtl' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array', 'max:10'],
            'options.*.label' => ['required_with:options', 'string', 'max:20'],
            'options.*.body' => ['required_with:options', 'string', 'max:2000'],
            'answer_key' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'jenis soal',
            'body' => 'isi soal',
            'image' => 'gambar soal',
            'options' => 'pilihan jawaban',
        ];
    }

    /**
     * @return array{type: string, body: string, is_rtl: bool, sort_order?: int, options?: list<array{label: string, body: string}>, answer_key?: array<string, mixed>|null}
     */
    public function questionData(): array
    {
        $validated = $this->validated();

        /** @var list<array{label: string, body: string}> $options */
        $options = array_values(array_map(
            static fn (array $opt): array => [
                'label' => (string) $opt['label'],
                'body' => (string) $opt['body'],
            ],
            (array) ($validated['options'] ?? []),
        ));

        $data = [
            'type' => (string) $validated['type'],
            'body' => (string) $validated['body'],
            'is_rtl' => (bool) $validated['is_rtl'],
            'options' => $options,
            'answer_key' => isset($validated['answer_key']) && is_array($validated['answer_key']) ? $validated['answer_key'] : null,
        ];

        if (isset($validated['sort_order'])) {
            $data['sort_order'] = (int) $validated['sort_order'];
        }

        return $data;
    }

    public function imageFile(): ?UploadedFile
    {
        $file = $this->file('image');

        return $file instanceof UploadedFile ? $file : null;
    }
}

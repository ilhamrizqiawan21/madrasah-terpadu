<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }

        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Subject|null $subject */
        $subject = $this->route('subject');
        $unitId = $subject !== null ? $subject->unit_id : $this->integer('unit_id');

        return [
            'unit_id' => $subject === null
                ? ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)]
                : ['prohibited'],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('subjects', 'code')
                    ->where('unit_id', $unitId)
                    ->ignore($subject?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unit_id' => 'unit',
            'code' => 'kode mapel',
            'name' => 'nama mapel',
        ];
    }

    /**
     * @return array{unit_id: int, code: string, name: string}
     */
    public function subjectData(): array
    {
        $validated = $this->validated();

        return [
            'unit_id' => (int) ($validated['unit_id'] ?? 0),
            'code' => (string) $validated['code'],
            'name' => (string) $validated['name'],
        ];
    }
}

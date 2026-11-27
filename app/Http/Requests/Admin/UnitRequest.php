<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UnitsManage->value) === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'code' => [
                'required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('units', 'code')->ignore($unit instanceof Unit ? $unit->id : null),
            ],
            'name' => ['required', 'string', 'max:100'],
            // Urutan jam (PRD §4.2): masuk < terlambat < alpa, dan pulang > terlambat.
            'check_in_opens_at' => ['required', 'date_format:H:i'],
            'late_after' => ['required', 'date_format:H:i', 'after:check_in_opens_at'],
            'absent_after' => ['required', 'date_format:H:i', 'after:late_after'],
            'check_out_opens_at' => ['required', 'date_format:H:i', 'after:late_after'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode unit',
            'name' => 'nama unit',
            'check_in_opens_at' => 'jam masuk dibuka',
            'late_after' => 'batas terlambat',
            'absent_after' => 'batas alpa',
            'check_out_opens_at' => 'jam pulang minimal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode unit hanya boleh berisi huruf dan angka tanpa spasi.',
            'late_after.after' => 'Batas terlambat harus setelah jam masuk dibuka.',
            'absent_after.after' => 'Batas alpa harus setelah batas terlambat.',
            'check_out_opens_at.after' => 'Jam pulang minimal harus setelah batas terlambat.',
        ];
    }

    /**
     * @return array{code: string, name: string, attendance_settings: array{check_in_opens_at: string, late_after: string, absent_after: string, check_out_opens_at: string}}
     */
    public function unitData(): array
    {
        /** @var array{code: string, name: string, check_in_opens_at: string, late_after: string, absent_after: string, check_out_opens_at: string} $validated */
        $validated = $this->validated();

        return [
            'code' => $validated['code'],
            'name' => trim($validated['name']),
            'attendance_settings' => [
                'check_in_opens_at' => $validated['check_in_opens_at'],
                'late_after' => $validated['late_after'],
                'absent_after' => $validated['absent_after'],
                'check_out_opens_at' => $validated['check_out_opens_at'],
            ],
        ];
    }
}

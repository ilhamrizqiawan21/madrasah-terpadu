<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitWeekendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'weekend_days' => ['present', 'array'],
            'weekend_days.*' => ['integer', 'between:1,7'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.required' => 'Unit wajib dipilih.',
            'weekend_days.*.between' => 'Hari akhir pekan harus bernilai 1 (Senin) sampai 7 (Minggu).',
        ];
    }
}

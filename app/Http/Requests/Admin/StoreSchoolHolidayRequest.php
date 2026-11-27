<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolHolidayRequest extends FormRequest
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
        $unitId = $this->filled('unit_id') ? $this->integer('unit_id') : null;

        return [
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'holiday_date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('school_holidays', 'holiday_date')
                    ->where(fn ($query) => $unitId === null
                        ? $query->whereNull('unit_id')
                        : $query->where('unit_id', $unitId)),
            ],
            'title' => ['required', 'string', 'max:120'],
            'is_day_off' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'holiday_date.required' => 'Tanggal wajib diisi.',
            'holiday_date.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'holiday_date.unique' => 'Tanggal ini sudah tercatat pada kalender unit tersebut.',
            'title.required' => 'Nama hari libur atau acara wajib diisi.',
            'title.max' => 'Nama hari libur maksimal 120 karakter.',
        ];
    }
}

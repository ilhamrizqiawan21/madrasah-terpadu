<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectAttendanceRequest extends FormRequest
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
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'school_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'string', Rule::enum(AttendanceStatus::class)],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa wajib dipilih.',
            'school_date.required' => 'Tanggal wajib diisi.',
            'status.required' => 'Status presensi wajib dipilih.',
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'reason.min' => 'Alasan koreksi minimal 3 karakter.',
        ];
    }
}

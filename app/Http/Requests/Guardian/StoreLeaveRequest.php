<?php

declare(strict_types=1);

namespace App\Http\Requests\Guardian;

use App\Enums\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::enum(LeaveType::class)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'attachment' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Jenis pengajuan wajib dipilih.',
            'starts_on.required' => 'Tanggal mulai wajib diisi.',
            'ends_on.required' => 'Tanggal selesai wajib diisi.',
            'ends_on.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
            'reason.required' => 'Alasan izin wajib diisi.',
            'reason.min' => 'Alasan izin minimal 5 karakter.',
            'attachment.image' => 'Lampiran harus berupa gambar.',
            'attachment.max' => 'Ukuran lampiran maksimal 2 MB.',
        ];
    }
}

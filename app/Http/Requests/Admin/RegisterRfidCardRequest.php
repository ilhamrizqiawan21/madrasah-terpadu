<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRfidCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'uid' => ['required', 'string', 'min:4', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'uid.required' => 'UID kartu RFID wajib diisi.',
            'uid.min' => 'UID kartu RFID minimal 4 karakter.',
            'uid.max' => 'UID kartu RFID maksimal 40 karakter.',
        ];
    }
}

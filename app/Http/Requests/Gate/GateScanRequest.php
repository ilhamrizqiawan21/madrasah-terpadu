<?php

declare(strict_types=1);

namespace App\Http\Requests\Gate;

use Illuminate\Foundation\Http\FormRequest;

class GateScanRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:128'],
            'device_label' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Token atau UID kartu wajib dipindai.',
            'token.max' => 'Format token kartu terlalu panjang.',
        ];
    }
}

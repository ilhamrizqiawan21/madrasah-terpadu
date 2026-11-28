<?php

declare(strict_types=1);

namespace App\Http\Requests\Canteen;

use Illuminate\Foundation\Http\FormRequest;

class LookupStudentCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }
}

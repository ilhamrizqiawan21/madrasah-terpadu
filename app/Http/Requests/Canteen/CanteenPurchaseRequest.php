<?php

declare(strict_types=1);

namespace App\Http\Requests\Canteen;

use Illuminate\Foundation\Http\FormRequest;

class CanteenPurchaseRequest extends FormRequest
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
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'total' => ['required', 'integer', 'min:100', 'max:5000000'],
            'client_uuid' => ['required', 'uuid'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.name' => ['required_with:items', 'string', 'max:100'],
            'items.*.amount' => ['required_with:items', 'integer', 'min:1'],
        ];
    }
}

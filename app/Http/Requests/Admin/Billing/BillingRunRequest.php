<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Enums\BillingTargetType;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('issue', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fee_type_id' => ['required', 'integer', 'exists:fee_types,id'],
            'target_type' => ['required', Rule::enum(BillingTargetType::class)],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'grade' => ['nullable', 'string', 'max:10'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'period_from' => ['nullable', 'date_format:Y-m'],
            'period_to' => ['nullable', 'date_format:Y-m'],
            'due_mode' => ['required', Rule::in(['day', 'date'])],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_date' => ['nullable', 'date'],
            'default_amount' => ['nullable', 'integer', 'min:0'],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['integer', 'min:0'],
        ];
    }
}

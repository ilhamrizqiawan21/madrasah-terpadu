<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Enums\DiscountKind;
use App\Models\StudentDiscount;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StudentDiscount|null $discount */
        $discount = $this->route('studentDiscount');
        $user = $this->user();

        return $discount !== null && $user instanceof User && $user->can('update', $discount);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'fee_type_id' => ['required', 'integer', 'exists:fee_types,id'],
            'kind' => ['required', Rule::enum(DiscountKind::class)],
            'value' => ['required', 'integer', 'min:1'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}

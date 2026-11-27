<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Enums\FeeRecurrence;
use App\Models\FeeType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FeeType|null $feeType */
        $feeType = $this->route('feeType');
        $user = $this->user();

        return $feeType !== null && $user instanceof User && $user->can('update', $feeType);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var FeeType|null $feeType */
        $feeType = $this->route('feeType');
        $unitId = $this->filled('unit_id') ? $this->integer('unit_id') : null;

        return [
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('fee_types', 'name')
                    ->ignore($feeType?->id)
                    ->where(
                        fn ($q) => $unitId === null
                            ? $q->whereNull('unit_id')
                            : $q->where('unit_id', $unitId),
                    ),
            ],
            'category' => ['required', 'string', 'max:30'],
            'recurrence' => ['required', Rule::enum(FeeRecurrence::class)],
            'default_amount' => ['required', 'integer', 'min:0'],
        ];
    }
}

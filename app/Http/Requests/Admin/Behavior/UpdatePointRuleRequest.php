<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Behavior;

use App\Enums\PointCategory;
use App\Models\PointRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePointRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PointRule|null $rule */
        $rule = $this->route('pointRule');
        $user = $this->user();

        return $rule !== null && $user instanceof User && $user->can('update', $rule);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::enum(PointCategory::class)],
            'level' => ['nullable', 'string', 'max:50'],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}

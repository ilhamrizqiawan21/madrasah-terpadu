<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Behavior;

use App\Models\BehaviorRecord;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreBehaviorRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', BehaviorRecord::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'exists:students,id'],
            'point_rule_id' => ['required', 'integer', 'exists:point_rules,id'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'image', 'max:4096'],
        ];
    }
}

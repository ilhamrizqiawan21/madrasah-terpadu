<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Character;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CorrectAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can(Permission::AttitudeView->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'final_level' => ['nullable', 'string', 'max:10'],
            'override_reason' => ['nullable', 'string', 'max:500'],
            'observation_level' => ['nullable', 'integer', 'between:1,4'],
            'description' => ['nullable', 'string', 'max:600'],
        ];
    }

    /**
     * @return array{final_level?: string|null, override_reason?: string|null, observation_level?: int|null, description?: string|null}
     */
    public function correction(): array
    {
        $data = [];

        foreach (['final_level', 'override_reason', 'description'] as $key) {
            if ($this->has($key)) {
                $value = $this->validated($key);
                $data[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }
        }

        if ($this->has('observation_level')) {
            $value = $this->validated('observation_level');
            $data['observation_level'] = $value !== null ? (int) $value : null;
        }

        /** @var array{final_level?: string|null, override_reason?: string|null, observation_level?: int|null, description?: string|null} */
        return $data;
    }
}

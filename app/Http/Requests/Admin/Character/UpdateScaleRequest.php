<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Character;

use App\Enums\Permission;
use App\Services\Character\CharacterSettingsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can(Permission::SettingsManage->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scale' => ['required', 'array', 'min:'.CharacterSettingsService::MIN_LEVELS, 'max:'.CharacterSettingsService::MAX_LEVELS],
            'scale.*.code' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'scale.*.label' => ['required', 'string', 'max:50'],
            'scale.*.min' => ['required', 'integer', 'min:0', 'max:100'],
            'coverage_min_pct' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return list<array{code: string, label: string, min: int}>
     */
    public function scale(): array
    {
        /** @var list<array{code: string, label: string, min: int|string}> $levels */
        $levels = $this->validated('scale');

        return array_map(fn (array $level): array => [
            'code' => mb_strtoupper(trim($level['code'])),
            'label' => trim($level['label']),
            'min' => (int) $level['min'],
        ], $levels);
    }

    public function coverage(): int
    {
        return (int) $this->validated('coverage_min_pct');
    }
}

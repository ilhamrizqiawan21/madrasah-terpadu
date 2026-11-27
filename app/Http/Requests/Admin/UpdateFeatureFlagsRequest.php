<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Feature;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFeatureFlagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can(Permission::SettingsManage->value) === true;
    }

    /**
     * Halaman selalu mengirim status semua modul, jadi setiap kunci wajib dan kunci asing ditolak.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $keys = array_map(fn (Feature $feature): string => $feature->value, Feature::cases());

        $rules = ['features' => ['required', 'array:'.implode(',', $keys)]];

        foreach ($keys as $key) {
            $rules['features.'.$key] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['features' => 'kunci fitur'];

        foreach (Feature::cases() as $feature) {
            $attributes['features.'.$feature->value] = $feature->label();
        }

        return $attributes;
    }

    /**
     * @return array<string, bool>
     */
    public function featureValues(): array
    {
        /** @var array{features: array<string, bool|int|string>} $validated */
        $validated = $this->validated();

        return array_map(
            fn (bool|int|string $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            $validated['features'],
        );
    }
}

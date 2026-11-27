<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Character;

use App\Enums\CharacterSource;
use App\Enums\HabitAspect;
use App\Enums\Permission;
use App\Models\CharacterProfile;
use App\Models\HabitItem;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveDimensionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'description_templates' => ['required', 'array'],
            'description_templates.*' => ['required', 'string', 'max:300'],
            'components' => ['required', 'array', 'min:1', 'max:6'],
            'components.*.source' => ['required', Rule::enum(CharacterSource::class)],
            'components.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'components.*.params' => ['nullable', 'array'],
            'components.*.params.aspect' => ['nullable', Rule::enum(HabitAspect::class)],
            'components.*.params.item_ids' => ['nullable', 'array', 'max:20'],
            'components.*.params.item_ids.*' => ['integer'],
            'components.*.params.tiers' => ['nullable', 'array', 'min:2', 'max:8'],
            'components.*.params.tiers.*.max' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'components.*.params.tiers.*.score' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama dimensi',
            'components' => 'komponen',
            'components.*.weight' => 'bobot',
            'components.*.source' => 'sumber data',
            'description_templates.*' => 'templat deskripsi',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $unit = $this->route('unit');
            $profile = $unit instanceof Unit ? CharacterProfile::query()->where('unit_id', $unit->id)->first() : null;

            if ($profile === null) {
                $validator->errors()->add('name', 'Pilih kurikulum unit ini terlebih dahulu.');

                return;
            }

            $codes = array_column($profile->scale, 'code');
            $templates = (array) $this->input('description_templates', []);

            foreach ($codes as $code) {
                if (! isset($templates[$code]) || trim((string) $templates[$code]) === '') {
                    $validator->errors()->add("description_templates.{$code}", "Templat deskripsi untuk tingkat {$code} wajib diisi.");
                }
            }

            /** @var array<int, array<string, mixed>> $components */
            $components = (array) $this->input('components', []);

            foreach ($components as $index => $component) {
                $source = CharacterSource::from((string) $component['source']);
                $params = (array) ($component['params'] ?? []);

                if ($source === CharacterSource::HabitsAspect && ! isset($params['aspect'])) {
                    $validator->errors()->add("components.{$index}.params.aspect", 'Pilih aspek pembiasaan.');
                }

                if ($source === CharacterSource::HabitsItems) {
                    $ids = array_map('intval', (array) ($params['item_ids'] ?? []));
                    $valid = $ids === [] ? 0 : HabitItem::query()->forUnit($unit->id)->whereIn('id', $ids)->count();

                    if ($ids === [] || $valid !== count(array_unique($ids))) {
                        $validator->errors()->add("components.{$index}.params.item_ids", 'Pilih item pembiasaan milik unit ini.');
                    }
                }

                if ($source->usesTiers()) {
                    $this->validateTiers($validator, $index, (array) ($params['tiers'] ?? []));
                }
            }
        }];
    }

    /**
     * @return array{name: string, is_active: bool, description_templates: array<string, string>, components: list<array{source: string, weight: int, params: array<string, mixed>}>}
     */
    public function dimensionData(): array
    {
        /** @var array<string, string> $templates */
        $templates = $this->validated('description_templates');
        /** @var list<array{source: string, weight: int|string, params?: array<string, mixed>|null}> $components */
        $components = $this->validated('components');

        return [
            'name' => trim((string) $this->validated('name')),
            'is_active' => (bool) $this->validated('is_active'),
            'description_templates' => array_map(fn (string $value): string => trim($value), $templates),
            'components' => array_map(function (array $component): array {
                $source = CharacterSource::from($component['source']);
                $params = (array) ($component['params'] ?? []);

                // Simpan hanya parameter yang dipakai sumbernya.
                $clean = match (true) {
                    $source === CharacterSource::HabitsAspect => ['aspect' => (string) $params['aspect']],
                    $source === CharacterSource::HabitsItems => ['item_ids' => array_values(array_unique(array_map('intval', (array) $params['item_ids'])))],
                    $source->usesTiers() => ['tiers' => array_values(array_map(fn (array $tier): array => [
                        'max' => isset($tier['max']) ? (int) $tier['max'] : null,
                        'score' => (int) $tier['score'],
                    ], (array) $params['tiers']))],
                    default => [],
                };

                return ['source' => $source->value, 'weight' => (int) $component['weight'], 'params' => $clean];
            }, $components),
        ];
    }

    /**
     * Tingkatan: batas `max` naik, hanya tingkatan terakhir boleh tanpa batas (null).
     *
     * @param  array<int, mixed>  $tiers
     */
    private function validateTiers(Validator $validator, int $index, array $tiers): void
    {
        $key = "components.{$index}.params.tiers";
        $tiers = array_values($tiers);

        if (count($tiers) < 2) {
            $validator->errors()->add($key, 'Tingkatan poin minimal dua baris.');

            return;
        }

        $previous = -1;

        foreach ($tiers as $position => $tier) {
            $max = is_array($tier) && isset($tier['max']) ? (int) $tier['max'] : null;
            $isLast = $position === count($tiers) - 1;

            if ($max === null && ! $isLast) {
                $validator->errors()->add($key, 'Hanya baris terakhir yang boleh tanpa batas poin.');

                return;
            }

            if ($max !== null && $max <= $previous) {
                $validator->errors()->add($key, 'Batas poin harus naik dari baris ke baris.');

                return;
            }

            $previous = $max ?? $previous;
        }

        if (is_array($tiers[count($tiers) - 1]) && isset($tiers[count($tiers) - 1]['max'])) {
            $validator->errors()->add($key, 'Baris terakhir harus tanpa batas ("lebih dari").');
        }
    }
}

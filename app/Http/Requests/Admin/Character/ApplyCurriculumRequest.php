<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Character;

use App\Enums\Curriculum;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyCurriculumRequest extends FormRequest
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
        return ['curriculum' => ['required', Rule::enum(Curriculum::class)]];
    }

    public function curriculum(): Curriculum
    {
        return Curriculum::from((string) $this->validated('curriculum'));
    }
}

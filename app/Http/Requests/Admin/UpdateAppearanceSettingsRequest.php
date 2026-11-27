<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Support\ThemeColor;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UpdateAppearanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `image` tidak menerima SVG (bisa memuat skrip), jadi aman disajikan dari domain aplikasi.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:256'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
            'reset_color' => ['sometimes', 'boolean'],
            'primary_color' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->boolean('reset_color') || $value === null || $value === '') {
                        return;
                    }

                    if (! is_string($value) || ! ThemeColor::isValidHex($value)) {
                        $fail('Warna utama harus berformat kode hex seperti #135B42.');

                        return;
                    }

                    if (! ThemeColor::passesWcagAa($value)) {
                        $fail('Warna utama tidak memenuhi kontras WCAG AA (minimal 4,5:1).');
                    }
                },
            ],
        ];
    }

    public function uploadedFile(string $key): ?UploadedFile
    {
        $file = $this->file($key);

        return $file instanceof UploadedFile ? $file : null;
    }
}

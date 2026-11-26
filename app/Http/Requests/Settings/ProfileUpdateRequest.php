<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $updates = [];

        if (config('fortify.lowercase_usernames') && is_string($this->input('email'))) {
            $updates['email'] = Str::lower($this->input('email'));
        }

        if ($this->has('phone')) {
            $phone = $this->input('phone');
            $updates['phone'] = is_string($phone) ? PhoneNumber::normalize($phone) : null;
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }
}

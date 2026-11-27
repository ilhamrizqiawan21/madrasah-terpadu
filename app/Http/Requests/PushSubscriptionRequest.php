<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url', 'max:500'],
            'keys' => ['nullable', 'array'],
            'keys.p256dh' => ['nullable', 'string', 'max:255'],
            'keys.auth' => ['nullable', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:32'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShortUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // already behind auth:sanctum + admin middleware
    }

    public function rules(): array
    {
        return [
            'long_url'   => ['sometimes', 'string', 'url', 'max:2048'],
            'is_active'  => ['sometimes', 'boolean'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'long_url.url'    => 'The destination must be a valid URL.',
            'expires_at.after' => 'The expiry date must be in the future.',
        ];
    }
}

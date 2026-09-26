<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreShortUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'long_url'   => ['required', 'string', 'url', 'max:2048'],
            'access_code' => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'long_url.required'    => 'A destination URL is required.',
            'long_url.url'         => 'The destination must be a valid URL.',
            'access_code.required' => 'An access code is required to shorten a URL.',
            'expires_at.after'     => 'The expiry date must be in the future.',
        ];
    }
}

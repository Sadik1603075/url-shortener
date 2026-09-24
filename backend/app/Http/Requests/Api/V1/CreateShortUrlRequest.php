<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateShortUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'long_url' => [
                'required',
                'url',
                'max:2048',
            ],

            'expires_at' => [
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }
}
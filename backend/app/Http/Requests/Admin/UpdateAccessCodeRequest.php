<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin gate handled by middleware
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['sometimes', 'boolean'],
            'expires_at'  => ['nullable', 'date', 'after:now'],
        ];
    }
}

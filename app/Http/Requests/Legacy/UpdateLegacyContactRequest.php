<?php

declare(strict_types=1);

namespace App\Http\Requests\Legacy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegacyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'relationship' => ['sometimes', 'string', 'max:100'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}

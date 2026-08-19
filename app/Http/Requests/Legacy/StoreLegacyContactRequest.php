<?php

declare(strict_types=1);

namespace App\Http\Requests\Legacy;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegacyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'relationship' => ['required', 'string', 'max:100'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}

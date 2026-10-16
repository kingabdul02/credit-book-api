<?php

declare(strict_types=1);

namespace App\Http\Requests\Legacy;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegacyAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_code' => ['nullable', 'string', 'size:8', 'regex:/^[A-Za-z0-9]+$/'],
            'reason' => ['nullable', 'string', 'max:500'],
            'recovery_code' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $accessCode = $validator->getData()['access_code'] ?? null;
            $recoveryCode = $validator->getData()['recovery_code'] ?? null;

            // Either access_code or recovery_code must be provided
            if (empty($accessCode) && empty($recoveryCode)) {
                $validator->errors()->add('access_code', 'Either access code or recovery code is required.');
                $validator->errors()->add('recovery_code', 'Either access code or recovery code is required.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'access_code.size' => 'The access code must be 8 characters.',
            'access_code.regex' => 'The access code format is invalid.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class PushSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'changes' => ['required', 'array', 'min:1', 'max:100'],
            'changes.*.entity_type' => ['required', 'string', 'in:contact,debt,transaction,reminder'],
            'changes.*.action' => ['required', 'string', 'in:CREATE,UPDATE,DELETE'],
            'changes.*.client_id' => ['required', 'uuid'],
            'changes.*.client_timestamp' => ['required', 'date'],
            'changes.*.payload' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'changes.max' => 'You can push a maximum of 100 changes at once.',
        ];
    }
}

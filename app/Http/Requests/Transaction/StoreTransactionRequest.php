<?php

declare(strict_types=1);

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'type' => ['sometimes', 'string', 'in:PAYMENT,ADDITIONAL'],
            'note' => ['nullable', 'string', 'max:1000'],
            'client_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'The payment amount must be at least 0.01.',
        ];
    }
}

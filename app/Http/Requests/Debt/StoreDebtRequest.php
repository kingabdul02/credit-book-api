<?php

declare(strict_types=1);

namespace App\Http\Requests\Debt;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'uuid', 'exists:contacts,id'],
            'transaction_type' => ['required', 'string', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'note' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'reminder_enabled' => ['sometimes', 'boolean'],
            'client_id' => ['required', 'uuid', 'unique:debts,client_id'],
            'transaction_client_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'The debt amount must be at least 0.01.',
            'contact_id.exists' => 'The selected contact does not exist.',
        ];
    }
}

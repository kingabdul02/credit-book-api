<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminder;

use App\Enums\ReminderSchedule;
use App\Enums\ReminderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'debt_id' => ['required', 'uuid', 'exists:debts,id'],
            'remind_at' => ['required', 'date', 'after:now'],
            'type' => ['required', 'string', Rule::enum(ReminderType::class)],
            'schedule' => ['required', 'string', Rule::enum(ReminderSchedule::class)],
            'client_id' => ['required', 'uuid', 'unique:reminders,client_id'],
        ];
    }
}

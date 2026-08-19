<?php

declare(strict_types=1);

namespace App\Http\Requests\Reminder;

use App\Enums\ReminderSchedule;
use App\Enums\ReminderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remind_at' => ['sometimes', 'date', 'after:now'],
            'type' => ['sometimes', 'string', Rule::enum(ReminderType::class)],
            'schedule' => ['sometimes', 'string', Rule::enum(ReminderSchedule::class)],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'transaction_type' => $this->transaction_type->value,
            'transaction_type_label' => $this->transaction_type->label(),
            'original_amount' => $this->original_amount,
            'paid_amount' => $this->paid_amount,
            'outstanding_amount' => $this->outstanding_amount,
            'progress_percentage' => $this->progress_percentage,
            'currency' => $this->currency,
            'note' => $this->note,
            'category' => $this->category,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'is_overdue' => $this->is_overdue,
            'reminder_enabled' => $this->reminder_enabled,
            'client_id' => $this->client_id,
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'reminders' => ReminderResource::collection($this->whenLoaded('reminders')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'debt_id' => $this->debt_id,
            'remind_at' => $this->remind_at?->toISOString(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'schedule' => $this->schedule->value,
            'schedule_label' => $this->schedule->label(),
            'is_sent' => $this->is_sent,
            'client_id' => $this->client_id,
            'debt' => new DebtResource($this->whenLoaded('debt')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

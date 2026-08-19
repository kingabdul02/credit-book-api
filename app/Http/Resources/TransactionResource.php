<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'debt_id' => $this->debt_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => $this->amount,
            'note' => $this->note,
            'client_id' => $this->client_id,
            'debt' => new DebtResource($this->whenLoaded('debt')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'phone_number' => $this->phone_number,
            'profile_picture' => $this->profile_picture,
            'note' => $this->note,
            'is_favorite' => $this->is_favorite,
            'usage_count' => $this->usage_count,
            'client_id' => $this->client_id,
            'total_owed_to_user' => $this->whenAppended('total_owed_to_user'),
            'total_user_owes' => $this->whenAppended('total_user_owes'),
            'debts_count' => $this->whenCounted('debts'),
            'debts' => DebtResource::collection($this->whenLoaded('debts')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

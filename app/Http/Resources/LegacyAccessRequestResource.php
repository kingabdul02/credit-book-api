<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegacyAccessRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legacy_contact' => new LegacyContactResource($this->whenLoaded('legacyContact')),
            'reason' => $this->reason,
            'recovery_code_provided' => $this->recovery_code_provided,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'grants_access' => $this->status?->grantsAccess(),
            'waiting_period_days' => $this->waiting_period_days,
            'requested_at' => $this->requested_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'denied_at' => $this->denied_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegacyContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAccountOwner = $user && $user->id === $this->user_id;

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'relationship' => $this->relationship,
            'is_primary' => $this->is_primary,
            'verification_status' => $this->verification_status?->value,
            'verified_at' => $this->verified_at?->toISOString(),
            'access_code' => $this->when($isAccountOwner, $this->access_code),
            'has_approved_access' => $this->whenAppended('has_approved_access'),
            'access_requests_count' => $this->whenCounted('accessRequests'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'default_currency' => $this->default_currency,
            'settings' => $this->settings,
            'has_pin' => (bool) $this->login_pin_hash,
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'two_factor_method' => $this->two_factor_method ?? 'authenticator',
            'has_google' => (bool) $this->google_id,
            'email_verified' => (bool) $this->email_verified_at,
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

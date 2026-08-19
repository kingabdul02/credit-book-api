<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LegacyAccessRequest;
use App\Models\User;

class LegacyAccessRequestPolicy
{
    /**
     * Determine if the user can view the access request.
     */
    public function view(User $user, LegacyAccessRequest $request): bool
    {
        return $user->id === $request->user_id;
    }

    /**
     * Determine if the user can approve the access request.
     */
    public function approve(User $user, LegacyAccessRequest $request): bool
    {
        return $user->id === $request->user_id;
    }

    /**
     * Determine if the user can deny the access request.
     */
    public function deny(User $user, LegacyAccessRequest $request): bool
    {
        return $user->id === $request->user_id;
    }
}

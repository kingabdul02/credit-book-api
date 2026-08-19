<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LegacyContact;
use App\Models\User;

class LegacyContactPolicy
{
    /**
     * Determine if the user can view the legacy contact.
     */
    public function view(User $user, LegacyContact $contact): bool
    {
        return $user->id === $contact->user_id;
    }

    /**
     * Determine if the user can update the legacy contact.
     */
    public function update(User $user, LegacyContact $contact): bool
    {
        return $user->id === $contact->user_id;
    }

    /**
     * Determine if the user can delete the legacy contact.
     */
    public function delete(User $user, LegacyContact $contact): bool
    {
        return $user->id === $contact->user_id;
    }
}

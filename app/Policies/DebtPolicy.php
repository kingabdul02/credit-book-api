<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;

class DebtPolicy
{
    /**
     * Determine if the user can view the debt.
     */
    public function view(User $user, Debt $debt): bool
    {
        return $user->id === $debt->user_id;
    }

    /**
     * Determine if the user can update the debt.
     */
    public function update(User $user, Debt $debt): bool
    {
        return $user->id === $debt->user_id;
    }

    /**
     * Determine if the user can delete the debt.
     */
    public function delete(User $user, Debt $debt): bool
    {
        return $user->id === $debt->user_id;
    }
}

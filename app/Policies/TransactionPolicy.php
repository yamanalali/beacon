<?php

namespace App\Policies;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;

/**
 * Bookkeeping access.
 *
 * Viewing follows enterprise access (SME members, assigned advisors, Beacon
 * admins). Recording is limited to active SME members of the enterprise.
 *
 * TODO: BUSINESS DECISION REQUIRED
 * Confirm whether Beacon advisors/admins may record or correct transactions
 * on behalf of an SME (e.g. during advisor sessions). Until confirmed they
 * have read-only access.
 */
class TransactionPolicy
{
    public function viewAny(User $user, Enterprise $enterprise): bool
    {
        return $user->can('view', $enterprise);
    }

    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $user->can('view', $transaction->enterprise);
    }

    public function create(User $user, Enterprise $enterprise): bool
    {
        return $user->isActive()
            && $user->role === UserRole::SmeUser
            && $enterprise->members()->whereKey($user->id)->exists();
    }

    public function correct(User $user, FinancialTransaction $transaction): bool
    {
        return $transaction->status === TransactionStatus::Posted
            && $this->create($user, $transaction->enterprise);
    }

    public function settle(User $user, FinancialTransaction $transaction): bool
    {
        return $this->correct($user, $transaction);
    }
}

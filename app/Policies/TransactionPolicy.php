<?php

namespace App\Policies;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;

/** Transaction permissions supplement enterprise assignments and role boundaries. */
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
        return $user->can('view', $enterprise)
            && $user->permission('record', $user->role === UserRole::SmeUser);
    }

    public function correct(User $user, FinancialTransaction $transaction): bool
    {
        return $transaction->status === TransactionStatus::Posted
            && ! $user->permission('require_approval', false)
            && $this->create($user, $transaction->enterprise);
    }

    public function approve(User $user, FinancialTransaction $transaction): bool
    {
        return $transaction->status === TransactionStatus::PendingApproval && $user->id !== $transaction->created_by
            && $user->can('view', $transaction->enterprise) && $user->permission('approve', $user->role === UserRole::BeaconAdmin);
    }

    public function settle(User $user, FinancialTransaction $transaction): bool
    {
        return $this->correct($user, $transaction);
    }
}

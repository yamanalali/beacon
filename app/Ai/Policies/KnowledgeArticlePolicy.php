<?php

namespace App\Ai\Policies;

use App\Ai\Models\KnowledgeArticle;
use App\Enums\UserRole;
use App\Models\User;

/** Only BEACON_ADMIN manages approved knowledge (rules §3, §37). */
class KnowledgeArticlePolicy
{
    public function manage(User $user): bool
    {
        return $user->isActive() && $user->role === UserRole::BeaconAdmin;
    }

    /** Reading published guidance: SME, advisor, admin. Not MFI/technical admin. */
    public function readPublished(User $user): bool
    {
        return $user->isActive() && in_array($user->role, [UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::BeaconAdmin], true);
    }
}

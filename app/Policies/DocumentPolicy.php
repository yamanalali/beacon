<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\User;

/**
 * Document access follows enterprise access exactly, for listing,
 * preview and download. MFI users and technical admins have no access
 * here (referral-package sharing will be a separate, consent-based path).
 */
class DocumentPolicy
{
    public function viewAny(User $user, Enterprise $enterprise): bool
    {
        return $user->can('view', $enterprise);
    }

    public function view(User $user, Document $document): bool
    {
        return $user->can('view', $document->enterprise);
    }

    public function upload(User $user, Enterprise $enterprise): bool
    {
        return $user->isActive()
            && in_array($user->role, [UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::BeaconAdmin], true)
            && $user->can('view', $enterprise);
    }

    /**
     * Metadata edit: same users who may upload to the enterprise.
     */
    public function update(User $user, Document $document): bool
    {
        return ! $document->isArchived() && $this->upload($user, $document->enterprise);
    }

    /**
     * Archive (soft, reversible by DB only): SME members of the enterprise
     * and Beacon admins. TODO: BUSINESS DECISION REQUIRED — confirm who may
     * archive documents (advisors are excluded for now).
     */
    public function archive(User $user, Document $document): bool
    {
        if ($document->isArchived() || ! $user->isActive() || ! $user->can('view', $document->enterprise)) {
            return false;
        }

        return in_array($user->role, [UserRole::SmeUser, UserRole::BeaconAdmin], true);
    }
}

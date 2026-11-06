<?php

namespace App\Notifications;

use App\Ai\Models\AiEscalation;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * In-app notice for the asker when Beacon staff reply to their escalation (rules §35/§43).
 * Database channel only — TODO: BUSINESS DECISION REQUIRED for other channels.
 */
class EscalationRepliedNotification extends Notification
{
    public function __construct(
        public readonly string $escalationPublicId,
        public readonly string $replyPreview,
        public readonly ?string $enterprisePublicId,
    ) {}

    public static function fromEscalation(AiEscalation $escalation): self
    {
        return new self(
            $escalation->public_id,
            Str::limit(trim((string) $escalation->staff_reply), 160),
            $escalation->enterprise?->public_id ?? ($escalation->context['enterprise_public_id'] ?? null),
        );
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'escalation_replied',
            'title' => 'رد على استفسارك',
            'message' => 'رد فريق بيكون على استفسارك: «'.$this->replyPreview.'».',
            'escalation_public_id' => $this->escalationPublicId,
            'enterprise_public_id' => $this->enterprisePublicId,
        ];
    }
}

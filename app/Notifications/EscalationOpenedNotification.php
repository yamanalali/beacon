<?php

namespace App\Notifications;

use App\Ai\Models\AiEscalation;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * In-app notice for assigned advisors when an SME escalates a question (rules §35/§43).
 * Database channel only — TODO: BUSINESS DECISION REQUIRED for other channels.
 */
class EscalationOpenedNotification extends Notification
{
    public function __construct(
        public readonly string $escalationPublicId,
        public readonly string $questionPreview,
        public readonly ?string $enterprisePublicId,
        public readonly ?string $enterpriseName,
    ) {}

    public static function fromEscalation(AiEscalation $escalation): self
    {
        $escalation->loadMissing('enterprise');

        return new self(
            $escalation->public_id,
            Str::limit(trim($escalation->question), 120),
            $escalation->enterprise?->public_id ?? ($escalation->context['enterprise_public_id'] ?? null),
            $escalation->enterprise?->business_name,
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
        $where = $this->enterpriseName
            ? ' لمنشأة '.$this->enterpriseName
            : '';

        return [
            'type' => 'escalation_opened',
            'title' => 'استفسار جديد يحتاج رداً',
            'message' => 'وصل استفسار جديد'.$where.' يحتاج مراجعة: «'.$this->questionPreview.'».',
            'escalation_public_id' => $this->escalationPublicId,
            'enterprise_public_id' => $this->enterprisePublicId,
        ];
    }
}

/** Shared presentation for in-app notifications (all types). */

export type NotificationData = {
    type?: string;
    title?: string;
    message?: string;
    enterprise_public_id?: string | null;
    due_date?: string | null;
    escalation_public_id?: string | null;
    [k: string]: unknown;
};

export const NOTIFICATION_ICONS: Record<string, string> = {
    compliance_deadline: "bi-calendar-event",
    escalation_opened: "bi-life-preserver",
    escalation_replied: "bi-chat-left-text",
};

export function notificationIcon(type?: string | null): string {
    return NOTIFICATION_ICONS[type ?? ""] ?? "bi-bell";
}

export function notificationHref(data: NotificationData): string | null {
    if (
        (data.type === "escalation_opened" || data.type === "escalation_replied") &&
        data.escalation_public_id
    ) {
        return `/ai/pages/escalations/${data.escalation_public_id}`;
    }
    if (data.type === "compliance_deadline" && data.enterprise_public_id) {
        return `/enterprises/${data.enterprise_public_id}/compliance`;
    }
    return null;
}

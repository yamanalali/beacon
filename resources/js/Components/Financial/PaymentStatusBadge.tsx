import { StatusBadge, type StatusTone } from "../Feedback/StatusBadge";

const tones: Record<string, StatusTone> = {
    paid: "success",
    partially_paid: "warning",
    unpaid: "danger",
};

export function PaymentStatusBadge({ status, label }: { status: string; label: string }) {
    return <StatusBadge label={label} tone={tones[status] ?? "neutral"} />;
}

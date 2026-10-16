export type StatusTone = "success" | "warning" | "danger" | "neutral";
const toneIcons: Record<StatusTone, string> = {
    success: "bi-check-circle",
    warning: "bi-hourglass-split",
    danger: "bi-exclamation-triangle",
    neutral: "bi-circle",
};
export function StatusBadge({
    label,
    tone,
}: {
    label: string;
    tone: StatusTone;
}) {
    return (
        <span className={`app-status app-status--${tone}`}>
            <i className={`bi ${toneIcons[tone]}`} aria-hidden="true" />
            <span>{label}</span>
        </span>
    );
}

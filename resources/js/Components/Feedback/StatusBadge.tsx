export type StatusTone = 'success' | 'warning' | 'danger' | 'neutral';

const toneIcons: Record<StatusTone, string> = {
    success: 'bi-check-circle',
    warning: 'bi-hourglass-split',
    danger: 'bi-exclamation-triangle',
    neutral: 'bi-circle',
};

const toneColors: Record<StatusTone, string> = {
    success: 'var(--app-success)',
    warning: 'var(--app-warning)',
    danger: 'var(--app-danger)',
    neutral: 'var(--app-text-muted)',
};

export function StatusBadge({ label, tone }: { label: string; tone: StatusTone }) {
    const color = toneColors[tone];

    return (
        <span
            className="d-inline-flex align-items-center gap-1 border px-2 py-1"
            style={{
                color,
                borderColor: color,
                backgroundColor: 'var(--app-surface, #ffffff)',
                borderRadius: 'var(--app-radius-sm, 0.5rem)',
                fontSize: '0.875rem',
                lineHeight: 1.4,
            }}
        >
            <i className={`bi ${toneIcons[tone]}`} aria-hidden="true" />
            <span>{label}</span>
        </span>
    );
}

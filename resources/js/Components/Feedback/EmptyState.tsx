export function EmptyState({
    title,
    description,
    actionLabel,
    onAction,
}: {
    title: string;
    description?: string;
    actionLabel?: string;
    onAction?: () => void;
}) {
    return (
        <div className="px-3 py-4 py-md-5 text-center" role="status">
            <h2
                className="mb-2 fw-semibold"
                style={{
                    fontSize: '1.125rem',
                    lineHeight: 1.4,
                    color: 'var(--app-text, #1c1917)',
                }}
            >
                {title}
            </h2>
            {description ? (
                <p
                    className="mx-auto mb-0"
                    style={{
                        maxWidth: '36rem',
                        fontSize: '1rem',
                        lineHeight: 1.6,
                        color: 'var(--app-text-muted, #57534e)',
                    }}
                >
                    {description}
                </p>
            ) : null}
            {actionLabel ? (
                <button
                    type="button"
                    className="btn btn-primary mt-3"
                    style={{ minHeight: '48px', minWidth: '44px', fontSize: '16px' }}
                    onClick={onAction}
                >
                    {actionLabel}
                </button>
            ) : null}
        </div>
    );
}

export function StatCard({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <article
            className="h-100 border p-3"
            style={{
                backgroundColor: 'var(--app-surface, #ffffff)',
                borderColor: 'var(--app-border, #e7e5e4)',
                borderRadius: 'var(--app-radius, 0.75rem)',
            }}
        >
            <p
                className="mb-1"
                style={{
                    fontSize: '0.875rem',
                    lineHeight: 1.5,
                    color: 'var(--app-text-muted, #57534e)',
                }}
            >
                {label}
            </p>
            <p
                className="mb-0 fw-semibold"
                style={{
                    fontSize: '1.5rem',
                    lineHeight: 1.3,
                    color: 'var(--app-text, #1c1917)',
                }}
            >
                {value}
            </p>
            {hint ? (
                <p
                    className="mb-0 mt-2"
                    style={{
                        fontSize: '0.875rem',
                        lineHeight: 1.5,
                        color: 'var(--app-text-muted, #57534e)',
                    }}
                >
                    {hint}
                </p>
            ) : null}
        </article>
    );
}

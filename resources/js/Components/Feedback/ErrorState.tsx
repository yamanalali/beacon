export function ErrorState({ title, description }: { title: string; description?: string }) {
    return (
        <div
            className="border p-3 p-md-4"
            role="alert"
            style={{
                backgroundColor: 'var(--app-surface, #ffffff)',
                borderColor: 'var(--app-danger)',
                borderRadius: 'var(--app-radius, 0.75rem)',
            }}
        >
            <div className="d-flex align-items-start gap-2">
                <i
                    className="bi bi-exclamation-triangle"
                    aria-hidden="true"
                    style={{
                        color: 'var(--app-danger)',
                        fontSize: '1.25rem',
                        lineHeight: 1.4,
                    }}
                />
                <div>
                    <h2
                        className="mb-1 fw-semibold"
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
                            className="mb-0"
                            style={{
                                fontSize: '1rem',
                                lineHeight: 1.6,
                                color: 'var(--app-text-muted, #57534e)',
                            }}
                        >
                            {description}
                        </p>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

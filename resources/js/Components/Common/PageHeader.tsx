export function PageHeader({ title, description }: { title: string; description?: string }) {
    return (
        <header className="mb-4">
            <h1
                className="mb-0 fw-semibold"
                style={{
                    fontSize: 'clamp(1.25rem, 2.2vw, 1.75rem)',
                    lineHeight: 1.35,
                    color: 'var(--app-text, #1c1917)',
                }}
            >
                {title}
            </h1>
            {description ? (
                <p
                    className="mb-0 mt-2"
                    style={{
                        fontSize: '1rem',
                        lineHeight: 1.6,
                        color: 'var(--app-text-muted, #57534e)',
                    }}
                >
                    {description}
                </p>
            ) : null}
        </header>
    );
}

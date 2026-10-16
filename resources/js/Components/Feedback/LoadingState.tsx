export function LoadingState({ label = 'جارٍ التحميل' }: { label?: string }) {
    return (
        <div className="d-flex align-items-center gap-2 py-3" role="status" aria-live="polite">
            <div
                className="spinner-border"
                aria-hidden="true"
                style={{
                    width: '1.25rem',
                    height: '1.25rem',
                    color: 'var(--app-primary, #1d4e4a)',
                }}
            />
            <span
                style={{
                    fontSize: '1rem',
                    lineHeight: 1.5,
                    color: 'var(--app-text, #1c1917)',
                }}
            >
                {label}
            </span>
        </div>
    );
}

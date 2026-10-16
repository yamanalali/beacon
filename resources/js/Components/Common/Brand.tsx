export function Brand({ compact = false }: { compact?: boolean }) {
    return (
        <span className="app-brand-lockup">
            <span className="app-brand-symbol" aria-hidden="true">
                <i className="bi bi-intersect" />
            </span>
            {compact ? null : (
                <span>
                    <span className="app-brand-name">
                        بيكون<span lang="en">BEACON</span>
                    </span>
                    <span className="app-brand-caption">
                        خطوة أوضح. أعمال أقوى.
                    </span>
                </span>
            )}
        </span>
    );
}

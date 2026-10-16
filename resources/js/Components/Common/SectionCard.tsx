import type { ReactNode } from 'react';

export function SectionCard({ title, children }: { title?: string; children: ReactNode }) {
    return (
        <section
            className="w-100 border"
            style={{
                backgroundColor: 'var(--app-surface, #ffffff)',
                borderColor: 'var(--app-border, #e7e5e4)',
                borderRadius: 'var(--app-radius, 0.75rem)',
            }}
        >
            {title ? (
                <h2
                    className="mb-0 px-3 px-md-4 pt-3 pt-md-4 fw-semibold"
                    style={{
                        fontSize: '1.125rem',
                        lineHeight: 1.4,
                        color: 'var(--app-text, #1c1917)',
                    }}
                >
                    {title}
                </h2>
            ) : null}
            <div className="p-3 p-md-4">{children}</div>
        </section>
    );
}

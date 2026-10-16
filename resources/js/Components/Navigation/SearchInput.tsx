import type { ChangeEvent } from 'react';

export function SearchInput({
    id,
    label,
    value,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="w-100">
            <label
                htmlFor={id}
                className="form-label mb-1 fw-semibold"
                style={{
                    fontSize: '0.9375rem',
                    color: 'var(--app-text, #1c1917)',
                }}
            >
                {label}
            </label>
            <div className="position-relative">
                <i
                    className="bi bi-search position-absolute"
                    aria-hidden="true"
                    style={{
                        insetInlineStart: '0.875rem',
                        top: '50%',
                        transform: 'translateY(-50%)',
                        color: 'var(--app-text-muted, #57534e)',
                        pointerEvents: 'none',
                    }}
                />
                <input
                    id={id}
                    type="search"
                    value={value}
                    className="form-control w-100"
                    style={{
                        minHeight: '48px',
                        fontSize: '16px',
                        paddingInlineStart: '2.75rem',
                    }}
                    onChange={(event: ChangeEvent<HTMLInputElement>) => onChange(event.target.value)}
                />
            </div>
        </div>
    );
}

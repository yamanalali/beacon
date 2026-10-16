import type { ChangeEvent } from 'react';

export function TextInput(props: {
    id: string;
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    type?: string;
    help?: string;
}) {
    const { id, label, name, value, onChange, error, type = 'text', help } = props;
    const helpId = help ? `${id}-help` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="mb-3 w-100">
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
            <input
                id={id}
                name={name}
                type={type}
                value={value}
                className={`form-control w-100${error ? ' is-invalid' : ''}`}
                style={{ minHeight: '48px', fontSize: '16px' }}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                onChange={(event: ChangeEvent<HTMLInputElement>) => onChange(event.target.value)}
            />
            {help ? (
                <div id={helpId} className="form-text" style={{ fontSize: '0.875rem' }}>
                    {help}
                </div>
            ) : null}
            {error ? (
                <div id={errorId} className="invalid-feedback d-block" role="alert" style={{ fontSize: '0.875rem' }}>
                    {error}
                </div>
            ) : null}
        </div>
    );
}

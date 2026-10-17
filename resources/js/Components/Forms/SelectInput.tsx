export type SelectOption = { value: string; label: string };

export function SelectInput(props: {
    id: string;
    label: string;
    value: string;
    options: SelectOption[];
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    required?: boolean;
}) {
    const { id, label, value, options, onChange, error, placeholder, required } = props;
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div className="mb-3 w-100">
            <label htmlFor={id} className="form-label mb-1 fw-semibold" style={{ fontSize: "0.9375rem" }}>
                {label}
                {required ? <span className="text-danger ms-1" aria-hidden="true">*</span> : null}
            </label>
            <select
                id={id}
                name={id}
                className={`form-select${error ? " is-invalid" : ""}`}
                style={{ minHeight: "48px", fontSize: "16px" }}
                value={value}
                required={required}
                aria-required={required || undefined}
                aria-invalid={error ? true : undefined}
                aria-describedby={errorId}
                onChange={(event) => onChange(event.target.value)}
            >
                {placeholder !== undefined ? <option value="">{placeholder}</option> : null}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error ? (
                <div id={errorId} className="invalid-feedback d-block" role="alert" style={{ fontSize: "0.875rem" }}>
                    {error}
                </div>
            ) : null}
        </div>
    );
}

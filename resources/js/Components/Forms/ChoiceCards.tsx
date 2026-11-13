export type ChoiceOption = {
    value: string;
    label: string;
    hint?: string;
};

export function ChoiceCards({
    id,
    label,
    value,
    options,
    onChange,
    error,
    required,
    columns = 2,
}: {
    id: string;
    label: string;
    value: string;
    options: ChoiceOption[];
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    columns?: 1 | 2;
}) {
    const errorId = error ? `${id}-error` : undefined;
    const groupName = id;

    return (
        <fieldset className={`choice-cards choice-cards--cols-${columns} mb-3 w-100`}>
            <legend className="choice-cards__legend">
                {label}
                {required ? <span className="text-danger ms-1" aria-hidden="true">*</span> : null}
            </legend>
            <div className="choice-cards__grid" role="radiogroup" aria-labelledby={`${id}-legend`} aria-required={required || undefined} aria-invalid={error ? true : undefined} aria-describedby={errorId}>
                <span id={`${id}-legend`} className="visually-hidden">
                    {label}
                </span>
                {options.map((option) => {
                    const inputId = `${id}-${option.value}`;
                    const checked = value === option.value;
                    return (
                        <label
                            key={option.value}
                            htmlFor={inputId}
                            className={`choice-cards__option${checked ? " is-selected" : ""}`}
                        >
                            <input
                                id={inputId}
                                type="radio"
                                name={groupName}
                                value={option.value}
                                checked={checked}
                                onChange={() => onChange(option.value)}
                            />
                            <span className="choice-cards__check" aria-hidden="true">
                                <i className={`bi ${checked ? "bi-check-circle-fill" : "bi-circle"}`} />
                            </span>
                            <span className="choice-cards__copy">
                                <strong>{option.label}</strong>
                                {option.hint ? <small>{option.hint}</small> : null}
                            </span>
                        </label>
                    );
                })}
            </div>
            {error ? (
                <div id={errorId} className="invalid-feedback d-block" role="alert">
                    {error}
                </div>
            ) : null}
        </fieldset>
    );
}

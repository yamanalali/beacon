import {
    useState,
    type ChangeEvent,
    type HTMLInputTypeAttribute,
    type InputHTMLAttributes,
} from "react";

export function TextInput(props: {
    id: string;
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    type?: HTMLInputTypeAttribute;
    help?: string;
    autoComplete?: string;
    list?: string;
    inputMode?: InputHTMLAttributes<HTMLInputElement>["inputMode"];
    required?: boolean;
    placeholder?: string;
}) {
    const {
        id,
        label,
        name,
        value,
        onChange,
        error,
        type = "text",
        help,
        autoComplete,
        inputMode,
        list,
        required,
        placeholder,
    } = props;
    const [visible, setVisible] = useState(false);
    const isPassword = type === "password";
    const helpId = help ? `${id}-help` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy =
        [helpId, errorId].filter(Boolean).join(" ") || undefined;
    const mode =
        inputMode ??
        (/amount/.test(name)
            ? "decimal"
            : /phone/.test(name)
              ? "tel"
              : undefined);

    return (
        <div className="mb-3 w-100">
            <label htmlFor={id} className="form-label">
                {label}
                {required ? <span className="text-danger ms-1" aria-hidden="true">*</span> : null}
            </label>
            <div className={isPassword ? "app-password-field" : undefined}>
                <input
                    id={id}
                    name={name}
                    type={isPassword && visible ? "text" : type}
                    value={value}
                    className={`form-control w-100${error ? " is-invalid" : ""}`}
                    autoComplete={autoComplete}
                    required={required}
                    aria-required={required || undefined}
                    placeholder={placeholder}
                    inputMode={mode}
                    list={list}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    onChange={(event: ChangeEvent<HTMLInputElement>) =>
                        onChange(event.target.value)
                    }
                />
                {isPassword ? (
                    <button
                        type="button"
                        className="app-password-toggle"
                        onClick={() => setVisible(!visible)}
                        aria-label={`${visible ? "إخفاء" : "إظهار"} ${label}`}
                        aria-pressed={visible}
                    >
                        <i
                            className={`bi ${visible ? "bi-eye-slash" : "bi-eye"}`}
                            aria-hidden="true"
                        />
                    </button>
                ) : null}
            </div>
            {help ? (
                <div id={helpId} className="form-text">
                    {help}
                </div>
            ) : null}
            {error ? (
                <div
                    id={errorId}
                    className="invalid-feedback d-block"
                    role="alert"
                >
                    {error}
                </div>
            ) : null}
        </div>
    );
}

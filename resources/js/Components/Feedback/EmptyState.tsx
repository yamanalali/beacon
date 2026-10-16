export function EmptyState({
    title,
    description,
    actionLabel,
    onAction,
    icon = "bi-inbox",
}: {
    title: string;
    description?: string;
    actionLabel?: string;
    onAction?: () => void;
    icon?: string;
}) {
    return (
        <div className="app-empty" role="status">
            <span className="app-empty__icon">
                <i className={`bi ${icon}`} aria-hidden="true" />
            </span>
            <h2>{title}</h2>
            {description ? <p>{description}</p> : null}
            {actionLabel ? (
                <button
                    type="button"
                    className="btn btn-primary mt-3"
                    onClick={onAction}
                >
                    {actionLabel}
                </button>
            ) : null}
        </div>
    );
}

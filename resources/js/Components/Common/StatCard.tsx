export function StatCard({
    label,
    value,
    hint,
    icon = "bi-bar-chart",
    tone = "teal",
}: {
    label: string;
    value: string;
    hint?: string;
    icon?: string;
    tone?: "teal" | "blue" | "amber" | "violet";
}) {
    return (
        <article className={`app-stat app-stat--${tone}`}>
            <div className="app-stat__top">
                <p>{label}</p>
                <span className="app-stat__icon">
                    <i className={`bi ${icon}`} aria-hidden="true" />
                </span>
            </div>
            <p className="app-stat__value">
                <bdi>{value}</bdi>
            </p>
            {hint ? <p className="app-stat__hint">{hint}</p> : null}
        </article>
    );
}

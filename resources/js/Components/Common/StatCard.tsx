export function StatCard({
    label,
    value,
    hint,
    tone = "teal",
}: {
    label: string;
    value: string;
    hint?: string;
    /** Kept for call-site compatibility; decorative icons removed in full unslop. */
    icon?: string;
    tone?: "teal" | "blue" | "amber" | "violet";
}) {
    const warn = tone === "amber";
    return (
        <article className={`app-stat${warn ? " app-stat--amber" : ""}`}>
            <p className="app-stat__label">{label}</p>
            <p className="app-stat__value">
                <bdi>{value}</bdi>
            </p>
            {hint ? <p className="app-stat__hint">{hint}</p> : null}
        </article>
    );
}

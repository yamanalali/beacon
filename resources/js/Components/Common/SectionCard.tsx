import { useId, type ReactNode } from "react";

export function SectionCard({
    title,
    children,
}: {
    title?: string;
    children: ReactNode;
}) {
    const titleId = useId();
    return (
        <section
            className="app-section"
            aria-labelledby={title ? titleId : undefined}
        >
            {title ? (
                <div className="app-section__header">
                    <h2 id={titleId}>{title}</h2>
                </div>
            ) : null}
            <div className="app-section__body">{children}</div>
        </section>
    );
}

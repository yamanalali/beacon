export function PageHeader({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <header className="app-page-header">
            <span className="app-eyebrow">
                <span aria-hidden="true" /> مساحة العمل
            </span>
            <h1>{title}</h1>
            {description ? <p>{description}</p> : null}
        </header>
    );
}

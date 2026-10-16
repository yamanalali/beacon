import { usePage } from "@inertiajs/react";

type DesktopTopbarProps = {
    title: string;
};

export function DesktopTopbar({ title }: DesktopTopbarProps) {
    const { auth } = usePage().props;
    const name = auth.user?.name.trim() || "زائر";

    return (
        <header className="app-topbar d-none d-lg-flex">
            <p className="app-topbar__title">{title}</p>
            <div className="app-topbar__actions">
                <button type="button" className="app-icon-button" aria-label="الإشعارات">
                    <i className="bi bi-bell" aria-hidden="true" />
                </button>
                <button type="button" className="app-profile" aria-label={`الملف الشخصي، ${name}`}>
                    <i className="bi bi-person-circle" aria-hidden="true" />
                    <span className="app-profile__name">{name}</span>
                </button>
            </div>
        </header>
    );
}

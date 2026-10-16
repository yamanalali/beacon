import { UserMenu } from "./UserMenu";

type DesktopTopbarProps = {
    title: string;
};

export function DesktopTopbar({ title }: DesktopTopbarProps) {
    return (
        <header className="app-topbar d-none d-lg-flex">
            <p className="app-topbar__title">{title}</p>
            <div className="app-topbar__actions">
                <button type="button" className="app-icon-button" aria-label="الإشعارات">
                    <i className="bi bi-bell" aria-hidden="true" />
                </button>
                <UserMenu />
            </div>
        </header>
    );
}

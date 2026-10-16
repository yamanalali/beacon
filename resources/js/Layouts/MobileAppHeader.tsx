import { usePage } from "@inertiajs/react";
import { UserMenu } from "./UserMenu";

type MobileAppHeaderProps = {
    title: string;
    showBack?: boolean;
};

export function MobileAppHeader({ title, showBack = false }: MobileAppHeaderProps) {
    const { dir } = usePage().props;
    const backIcon = dir === "rtl" ? "bi-arrow-right" : "bi-arrow-left";

    return (
        <header className="app-mobile-header d-flex d-lg-none">
            {showBack ? (
                <button
                    type="button"
                    className="app-icon-button"
                    aria-label="رجوع"
                    onClick={() => window.history.back()}
                >
                    <i className={`bi ${backIcon}`} aria-hidden="true" />
                </button>
            ) : null}
            <p className="app-mobile-header__title">{title}</p>
            <button type="button" className="app-icon-button" aria-label="الإشعارات">
                <i className="bi bi-bell" aria-hidden="true" />
            </button>
            <UserMenu compact />
        </header>
    );
}

import { Link } from "@inertiajs/react";
import { LanguageSwitcher } from "../Components/Common/LanguageSwitcher";
import { ConnectionStatus } from "../Components/Feedback/ConnectionStatus";
import { useT } from "../Hooks/useT";
import { NotificationMenu } from "./NotificationMenu";
import { UserMenu } from "./UserMenu";

export function DesktopTopbar({ title }: { title: string }) {
    const t = useT();

    return (
        <header className="app-topbar d-none d-lg-flex">
            <nav aria-label={t("shell.breadcrumb")} className="app-breadcrumb">
                <Link href="/" aria-label={t("shell.brand_home")}>
                    <i className="bi bi-house-door" aria-hidden="true" />
                </Link>
                <i className="bi bi-chevron-left" aria-hidden="true" />
                <span aria-current="page">{title}</span>
            </nav>
            <div className="app-topbar__actions">
                <LanguageSwitcher className="app-topbar__language" />
                <ConnectionStatus />
                <NotificationMenu />
                <UserMenu />
            </div>
        </header>
    );
}

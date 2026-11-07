import { Link, usePage } from "@inertiajs/react";
import { Brand } from "../Components/Common/Brand";
import { ConnectionStatus } from "../Components/Feedback/ConnectionStatus";
import { useT } from "../Hooks/useT";
import { NotificationMenu } from "./NotificationMenu";
import { UserMenu } from "./UserMenu";

export function MobileAppHeader({
    title,
    showBack = false,
}: {
    title: string;
    showBack?: boolean;
}) {
    const { dir } = usePage().props;
    const t = useT();

    return (
        <header className="app-mobile-header d-flex d-lg-none">
            {showBack ? (
                <button
                    type="button"
                    className="app-icon-button"
                    aria-label={t("common.back")}
                    onClick={() => window.history.back()}
                >
                    <i
                        className={`bi ${dir === "rtl" ? "bi-arrow-right" : "bi-arrow-left"}`}
                        aria-hidden="true"
                    />
                </button>
            ) : (
                <Link
                    href="/"
                    className="app-mobile-brand"
                    aria-label={t("shell.brand_home")}
                >
                    <Brand compact />
                </Link>
            )}
            <p className="app-mobile-header__title">{title}</p>
            <div className="d-flex align-items-center gap-1 gap-sm-2">
                <ConnectionStatus />
                <NotificationMenu />
                <UserMenu compact />
            </div>
        </header>
    );
}

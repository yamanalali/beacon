import { Link, usePage } from "@inertiajs/react";
import { Brand } from "../Components/Common/Brand";
import { UserMenu } from "./UserMenu";

export function MobileAppHeader({
    title,
    showBack = false,
}: {
    title: string;
    showBack?: boolean;
}) {
    const { dir } = usePage().props;
    return (
        <header className="app-mobile-header d-flex d-lg-none">
            {showBack ? (
                <button
                    type="button"
                    className="app-icon-button"
                    aria-label="رجوع"
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
                    aria-label="بيكون، الرئيسية"
                >
                    <Brand compact />
                </Link>
            )}
            <p className="app-mobile-header__title">{title}</p>
            <UserMenu compact />
        </header>
    );
}

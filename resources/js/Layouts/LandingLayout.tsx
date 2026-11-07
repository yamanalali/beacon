import { Head, Link, usePage } from "@inertiajs/react";
import { useEffect, useState, type ReactNode } from "react";
import { Brand } from "../Components/Common/Brand";
import { LanguageSwitcher } from "../Components/Common/LanguageSwitcher";
import { useT } from "../Hooks/useT";

const NAV = [
    { href: "/", key: "home" },
    { href: "/about", key: "about" },
    { href: "/case-studies", key: "case_studies" },
    { href: "/knowledge", key: "knowledge" },
    { href: "/contact", key: "contact" },
] as const;

type Props = {
    title: string;
    children: ReactNode;
};

export function LandingLayout({ title, children }: Props) {
    const t = useT();
    const page = usePage();
    const { locale, dir } = page.props as { locale: string; dir: string };
    const url = page.url;
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }, [dir, locale]);

    useEffect(() => {
        setMenuOpen(false);
    }, [url]);

    const isActive = (href: string) => {
        if (href === "/") {
            return url === "/" || url === "";
        }
        return url === href || url.startsWith(`${href}/`);
    };

    return (
        <div className="landing-page" dir={dir} lang={locale}>
            <Head title={title} />

            <header className="landing-header">
                <div className="landing-header-inner">
                    <Link href="/" className="landing-brand text-decoration-none" aria-label={t("common.app_name")}>
                        <Brand />
                    </Link>

                    <nav className="landing-nav d-none d-lg-flex" aria-label={t("landing.nav.primary")}>
                        {NAV.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={`landing-nav-link${isActive(item.href) ? " is-active" : ""}`}
                            >
                                {t(`landing.nav.${item.key}`)}
                            </Link>
                        ))}
                    </nav>

                    <div className="landing-header-actions">
                        <LanguageSwitcher />
                        <Link className="landing-nav-login d-none d-sm-inline-flex" href="/login">
                            {t("landing.hero.cta_login")}
                        </Link>
                        <Link className="btn landing-btn landing-btn-accent d-none d-lg-inline-flex" href="/contact">
                            {t("landing.hero.cta_contact")}
                        </Link>
                        <button
                            type="button"
                            className="landing-menu-toggle d-lg-none"
                            aria-expanded={menuOpen}
                            aria-controls="landing-mobile-nav"
                            aria-label={t("landing.nav.menu")}
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            <i className={`bi ${menuOpen ? "bi-x-lg" : "bi-list"}`} aria-hidden="true" />
                        </button>
                    </div>
                </div>

                {menuOpen ? (
                    <nav id="landing-mobile-nav" className="landing-mobile-nav d-lg-none" aria-label={t("landing.nav.primary")}>
                        {NAV.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={`landing-mobile-link${isActive(item.href) ? " is-active" : ""}`}
                            >
                                {t(`landing.nav.${item.key}`)}
                            </Link>
                        ))}
                        <Link className="btn landing-btn landing-btn-accent landing-mobile-cta" href="/contact">
                            {t("landing.hero.cta_contact")}
                        </Link>
                    </nav>
                ) : null}
            </header>

            <main className="landing-main">{children}</main>

            <footer className="landing-footer">
                <div className="landing-footer-inner">
                    <div className="landing-footer-brand">
                        <Brand compact />
                        <p className="landing-footer-copy">{t("landing.footer.copyright")}</p>
                    </div>
                    <nav className="landing-footer-nav" aria-label={t("landing.nav.footer")}>
                        {NAV.map((item) => (
                            <Link key={item.key} href={item.href} className="landing-footer-link">
                                {t(`landing.nav.${item.key}`)}
                            </Link>
                        ))}
                        <a href="mailto:info@beacon-cd.com" className="landing-footer-link">
                            info@beacon-cd.com
                        </a>
                        <a href="https://beacon-cd.com/" target="_blank" rel="noreferrer" className="landing-footer-link">
                            beacon-cd.com
                        </a>
                    </nav>
                </div>
            </footer>
        </div>
    );
}

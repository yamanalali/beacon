import { Head, Link, usePage } from "@inertiajs/react";
import { useEffect, type ReactNode } from "react";
import { Brand } from "../../Components/Common/Brand";
import { LanguageSwitcher } from "../../Components/Common/LanguageSwitcher";
import { useT } from "../../Hooks/useT";

export function GuestAuthLayout({
    title,
    intro,
    eyebrow,
    cardClassName = "",
    showHomeLink = true,
    children,
}: {
    title: string;
    intro?: string;
    eyebrow?: string;
    cardClassName?: string;
    showHomeLink?: boolean;
    children: ReactNode;
}) {
    const { locale, dir } = usePage().props as { locale?: string; dir?: string };
    const t = useT();
    const appName = t("common.app_name");
    const introText = intro ?? t("auth.guest.intro");
    const eyebrowText = eyebrow ?? t("auth.guest.welcome", { app: appName });
    const homeLabel = t("auth.guest.back_home");

    useEffect(() => {
        if (locale) document.documentElement.lang = locale;
        if (dir) document.documentElement.dir = dir;
    }, [dir, locale]);

    return (
        <div className="app-auth" dir={dir} lang={locale}>
            <Head title={`${title} | ${appName}`} />
            <aside className="app-auth-story" aria-label={t("auth.guest.about_aria")}>
                <Link href="/" className="app-auth-brand-link" aria-label={homeLabel}>
                    <Brand />
                </Link>
                <div className="app-auth-story__copy">
                    <span className="app-auth-eyebrow">{t("auth.guest.story_eyebrow")}</span>
                    <h2>
                        {t("auth.guest.headline_line1")}
                        <br />
                        {t("auth.guest.headline_line2")}
                    </h2>
                    <p>{t("auth.guest.story_body")}</p>
                    <div className="app-auth-features">
                        <span>
                            <i className="bi bi-wallet2" aria-hidden="true" />
                            {t("auth.guest.feature.records")}
                        </span>
                        <span>
                            <i className="bi bi-graph-up-arrow" aria-hidden="true" />
                            {t("auth.guest.feature.decisions")}
                        </span>
                        <span>
                            <i className="bi bi-buildings" aria-hidden="true" />
                            {t("auth.guest.feature.business")}
                        </span>
                    </div>
                </div>
                <p className="app-auth-story__footer">{t("auth.guest.story_footer", { app: appName })}</p>
            </aside>
            <main className="app-auth-main">
                <div className="app-auth-toolbar">
                    {showHomeLink ? (
                        <Link href="/" className="app-auth-back">
                            <i
                                className={`bi ${dir === "ltr" ? "bi-arrow-left" : "bi-arrow-right"}`}
                                aria-hidden="true"
                            />
                            <span>{homeLabel}</span>
                        </Link>
                    ) : (
                        <span />
                    )}
                    <LanguageSwitcher />
                </div>
                <div className="app-auth-mobile-brand">
                    <Link href="/" className="app-auth-brand-link" aria-label={homeLabel}>
                        <Brand />
                    </Link>
                </div>
                <section className={`app-auth-card ${cardClassName}`.trim()}>
                    <span className="app-eyebrow">
                        <span aria-hidden="true" /> {eyebrowText}
                    </span>
                    <h1>{title}</h1>
                    {introText ? <p className="app-auth-intro">{introText}</p> : null}
                    {children}
                </section>
                <p className="app-auth-footer">{t("auth.guest.platform_footer", { app: appName })}</p>
            </main>
        </div>
    );
}

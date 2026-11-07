import { Head, usePage } from "@inertiajs/react";
import { useEffect } from "react";
import { Brand } from "../../Components/Common/Brand";
import { useT } from "../../Hooks/useT";

const ERROR_ICONS: Record<number, string> = {
    403: "bi-shield-lock",
    404: "bi-signpost-split",
    419: "bi-hourglass-split",
    429: "bi-speedometer2",
    500: "bi-exclamation-octagon",
    503: "bi-tools",
};

const ERROR_KEYS: Record<number, string> = {
    403: "403",
    404: "404",
    419: "419",
    429: "429",
    500: "500",
    503: "503",
};

export default function ErrorPage({
    status,
    homeUrl,
    isGuest,
}: {
    status: number;
    homeUrl: string;
    isGuest: boolean;
}) {
    const { locale, dir } = usePage().props as { locale?: string; dir?: string };
    const t = useT();
    const appName = t("common.app_name");
    const code = ERROR_KEYS[status] ?? "500";
    const title = t(`errors.${code}.title`);
    const body = t(`errors.${code}.body`);
    const icon = ERROR_ICONS[status] ?? ERROR_ICONS[500];

    useEffect(() => {
        if (locale) document.documentElement.lang = locale;
        if (dir) document.documentElement.dir = dir;
    }, [dir, locale]);

    const primaryCta = isGuest ? t("errors.go_login") : t("errors.go_dashboard");
    const showRefresh = status === 419 || status === 429 || status === 503;

    return (
        <div className="app-auth" dir={dir} lang={locale}>
            <Head title={`${title} | ${appName}`} />
            <main className="app-auth-main" style={{ gridColumn: "1 / -1" }}>
                <div className="app-auth-mobile-brand d-flex justify-content-center">
                    <Brand />
                </div>
                <section className="app-auth-card text-center" role="alert">
                    <i
                        className={`bi ${icon}`}
                        aria-hidden="true"
                        style={{ fontSize: "2.75rem", color: "var(--app-primary, #176b60)" }}
                    />
                    <span className="app-eyebrow d-block mt-2" style={{ fontVariantNumeric: "tabular-nums" }}>
                        {status}
                    </span>
                    <h1>{title}</h1>
                    <p className="app-auth-intro">{body}</p>
                    <div className="d-flex flex-wrap justify-content-center gap-2 mt-3">
                        <a href={homeUrl} className="btn btn-primary">
                            <i className="bi bi-house-door ms-1" aria-hidden="true" />
                            {primaryCta}
                        </a>
                        {showRefresh ? (
                            <button type="button" className="btn btn-outline-secondary" onClick={() => window.location.reload()}>
                                {t("errors.refresh")}
                            </button>
                        ) : null}
                    </div>
                </section>
            </main>
        </div>
    );
}

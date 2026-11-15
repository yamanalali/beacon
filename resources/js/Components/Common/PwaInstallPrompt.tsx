import { useEffect, useState } from "react";
import { useT } from "../../Hooks/useT";

const STORAGE_KEY = "beacon_pwa_install_dismissed";
const SHOW_DELAY_MS = 1800;

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
};

function isStandalone(): boolean {
    if (typeof window === "undefined") {
        return true;
    }

    return (
        window.matchMedia("(display-mode: standalone)").matches ||
        window.matchMedia("(display-mode: fullscreen)").matches ||
        // iOS Safari installed PWA
        ("standalone" in navigator &&
            Boolean((navigator as Navigator & { standalone?: boolean }).standalone))
    );
}

function isMobileViewport(): boolean {
    return window.matchMedia("(max-width: 991.98px)").matches;
}

function isIosSafari(): boolean {
    const ua = window.navigator.userAgent;
    const iOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
    const webkit = /WebKit/.test(ua);
    const chrome = /CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);

    return iOS && webkit && !chrome;
}

export function PwaInstallPrompt() {
    const t = useT();
    const [visible, setVisible] = useState(false);
    const [mode, setMode] = useState<"android" | "ios" | null>(null);
    const [deferred, setDeferred] = useState<BeforeInstallPromptEvent | null>(null);
    const [installing, setInstalling] = useState(false);

    useEffect(() => {
        if (isStandalone() || !isMobileViewport()) {
            return;
        }

        try {
            if (window.localStorage.getItem(STORAGE_KEY) === "1") {
                return;
            }
        } catch {
            // Private mode — still allow the prompt once per session.
        }

        let timer: number | undefined;
        let cancelled = false;

        const reveal = (next: "android" | "ios") => {
            if (cancelled || !isMobileViewport() || isStandalone()) {
                return;
            }
            setMode(next);
            timer = window.setTimeout(() => {
                if (!cancelled) {
                    setVisible(true);
                }
            }, SHOW_DELAY_MS);
        };

        const onBeforeInstall = (event: Event) => {
            event.preventDefault();
            setDeferred(event as BeforeInstallPromptEvent);
            reveal("android");
        };

        window.addEventListener("beforeinstallprompt", onBeforeInstall);

        if (isIosSafari()) {
            reveal("ios");
        }

        const onMedia = () => {
            if (!isMobileViewport() || isStandalone()) {
                setVisible(false);
            }
        };
        const media = window.matchMedia("(max-width: 991.98px)");
        media.addEventListener("change", onMedia);

        return () => {
            cancelled = true;
            if (timer) {
                window.clearTimeout(timer);
            }
            window.removeEventListener("beforeinstallprompt", onBeforeInstall);
            media.removeEventListener("change", onMedia);
        };
    }, []);

    function dismiss() {
        setVisible(false);
        try {
            window.localStorage.setItem(STORAGE_KEY, "1");
        } catch {
            // ignore
        }
    }

    async function install() {
        if (!deferred) {
            return;
        }

        setInstalling(true);
        try {
            await deferred.prompt();
            await deferred.userChoice;
        } finally {
            setDeferred(null);
            setInstalling(false);
            dismiss();
        }
    }

    if (!visible || !mode) {
        return null;
    }

    return (
        <div className="pwa-install" role="dialog" aria-labelledby="pwa-install-title" aria-modal="false">
            <div className="pwa-install__card">
                <button
                    type="button"
                    className="pwa-install__close"
                    onClick={dismiss}
                    aria-label={t("pwa.install.dismiss")}
                >
                    <i className="bi bi-x-lg" aria-hidden="true" />
                </button>

                <div className="pwa-install__icon" aria-hidden="true">
                    <img src="/icons/icon-192.png" alt="" width={48} height={48} />
                </div>

                <div className="pwa-install__body">
                    <p id="pwa-install-title" className="pwa-install__title">
                        {t("pwa.install.title")}
                    </p>
                    <p className="pwa-install__text">
                        {mode === "ios" ? t("pwa.install.ios_body") : t("pwa.install.body")}
                    </p>

                    {mode === "ios" ? (
                        <ol className="pwa-install__steps">
                            <li>
                                {t("pwa.install.ios_step1_before")}
                                <i className="bi bi-box-arrow-up pwa-install__share" aria-hidden="true" />
                                {t("pwa.install.ios_step1_after")}
                            </li>
                            <li>{t("pwa.install.ios_step2")}</li>
                        </ol>
                    ) : null}

                    <div className="pwa-install__actions">
                        {mode === "android" ? (
                            <button
                                type="button"
                                className="btn btn-primary pwa-install__cta"
                                onClick={() => void install()}
                                disabled={installing || !deferred}
                            >
                                {installing ? (
                                    <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
                                ) : (
                                    <i className="bi bi-download" aria-hidden="true" />
                                )}
                                <span>{t("pwa.install.cta")}</span>
                            </button>
                        ) : (
                            <button type="button" className="btn btn-primary pwa-install__cta" onClick={dismiss}>
                                <i className="bi bi-check2" aria-hidden="true" />
                                <span>{t("pwa.install.got_it")}</span>
                            </button>
                        )}
                        <button type="button" className="btn btn-link pwa-install__later" onClick={dismiss}>
                            {t("pwa.install.later")}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

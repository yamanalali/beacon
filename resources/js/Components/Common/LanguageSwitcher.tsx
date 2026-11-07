import { useForm, usePage } from "@inertiajs/react";
import { useEffect, useId, useRef } from "react";
import { useT } from "../../Hooks/useT";

type LocaleCode = "ar" | "en";

const LOCALES: Array<{
    code: LocaleCode;
    native: string;
    secondaryAr: string;
    secondaryEn: string;
    short: string;
}> = [
    { code: "ar", native: "العربية", secondaryAr: "Arabic", secondaryEn: "Arabic", short: "AR" },
    { code: "en", native: "English", secondaryAr: "الإنجليزية", secondaryEn: "English", short: "EN" },
];

export function LanguageSwitcher({ className = "" }: { className?: string }) {
    const { locale, dir } = usePage().props as { locale: string; dir?: string };
    const t = useT();
    const current: LocaleCode = locale === "en" ? "en" : "ar";
    const active = LOCALES.find((item) => item.code === current) ?? LOCALES[0];
    const form = useForm({ locale: current });
    const menu = useRef<HTMLDetailsElement>(null);
    const listId = useId();

    useEffect(() => {
        if (menu.current) {
            menu.current.open = false;
        }
    }, [locale]);

    useEffect(() => {
        const dismiss = (e: PointerEvent) => {
            if (menu.current && !menu.current.contains(e.target as Node)) {
                menu.current.open = false;
            }
        };
        const escape = (e: KeyboardEvent) => {
            if (e.key === "Escape" && menu.current?.open) {
                menu.current.open = false;
                menu.current.querySelector("summary")?.focus();
            }
        };
        document.addEventListener("pointerdown", dismiss);
        document.addEventListener("keydown", escape);
        return () => {
            document.removeEventListener("pointerdown", dismiss);
            document.removeEventListener("keydown", escape);
        };
    }, []);

    function select(next: LocaleCode) {
        if (next === current || form.processing) {
            if (menu.current) {
                menu.current.open = false;
            }
            return;
        }
        form.setData("locale", next);
        form.post("/locale", {
            preserveScroll: true,
            onFinish: () => {
                if (menu.current) {
                    menu.current.open = false;
                }
            },
        });
    }

    return (
        <details
            ref={menu}
            className={`language-switcher${form.processing ? " is-busy" : ""} ${className}`.trim()}
            dir={dir}
        >
            <summary
                className="language-switcher__trigger"
                aria-label={`${t("common.language")}: ${active.native}`}
                aria-controls={listId}
                aria-haspopup="listbox"
                aria-busy={form.processing || undefined}
            >
                <span className="language-switcher__icon" aria-hidden="true">
                    <i className="bi bi-globe2" />
                </span>
                <span className="language-switcher__copy">
                    <span className="language-switcher__code">{active.short}</span>
                    <span className="language-switcher__name">{active.native}</span>
                </span>
                <i
                    className={`bi language-switcher__chevron ${form.processing ? "bi-arrow-repeat" : "bi-chevron-down"}`}
                    aria-hidden="true"
                />
            </summary>

            <div
                id={listId}
                className="language-switcher__menu"
                role="listbox"
                aria-label={t("common.language")}
            >
                <p className="language-switcher__heading">{t("common.language")}</p>
                {LOCALES.map((item) => {
                    const selected = item.code === current;
                    const secondary = current === "ar" ? item.secondaryAr : item.secondaryEn;
                    return (
                        <button
                            key={item.code}
                            type="button"
                            role="option"
                            aria-selected={selected}
                            className={`language-switcher__option${selected ? " is-active" : ""}`}
                            disabled={form.processing}
                            onClick={() => select(item.code)}
                        >
                            <span className="language-switcher__option-main">
                                <span className="language-switcher__option-native">{item.native}</span>
                                <span className="language-switcher__option-meta">{secondary}</span>
                            </span>
                            <span className="language-switcher__option-badge" aria-hidden="true">
                                {item.short}
                            </span>
                            {selected ? (
                                <i className="bi bi-check2 language-switcher__check" aria-hidden="true" />
                            ) : (
                                <span className="language-switcher__check-spacer" aria-hidden="true" />
                            )}
                        </button>
                    );
                })}
            </div>
        </details>
    );
}

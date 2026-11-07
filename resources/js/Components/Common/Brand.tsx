import { useT } from "../../Hooks/useT";

export function Brand({ compact = false }: { compact?: boolean }) {
    const t = useT();

    return (
        <span className="app-brand-lockup">
            <img
                className="app-brand-logo"
                src="/images/brand/beacon-logo.png"
                alt={t("common.app_name")}
                width={compact ? 120 : 168}
                height={compact ? 65 : 91}
                decoding="async"
            />
            {compact ? null : (
                <span className="visually-hidden">{t("common.brand_tagline_ar")}</span>
            )}
        </span>
    );
}

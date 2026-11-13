import { useId, useMemo, useState } from "react";
import { useT } from "../../Hooks/useT";
import { SectionCard } from "../Common/SectionCard";

export type BaselineField = {
    key: string;
    label: string;
    value: string | number;
    kind: string;
    values?: string[];
};
export type BaselineSection = { key: string; label: string; fields: BaselineField[] };
export type BaselinePayload = {
    has_profile: boolean;
    external_mse_id: string | null;
    imported_at: string | null;
    highlight?: Array<{ key: string; label: string; value: string }>;
    sections: BaselineSection[];
};

function isAffirmative(value: string): boolean {
    const v = value.trim().toLowerCase();
    return ["نعم", "yes", "y", "true", "1"].includes(v);
}

function isNegative(value: string): boolean {
    const v = value.trim().toLowerCase();
    return ["لا", "no", "n", "false", "0"].includes(v);
}

function splitValues(field: BaselineField): string[] {
    if (field.values && field.values.length > 0) {
        return field.values;
    }
    const raw = String(field.value ?? "").trim();
    if (!raw) {
        return [];
    }
    if (field.kind === "multi_choice") {
        const parts = raw
            .split(/\n+|؛\s*|;\s*|\s*\|\s*|،\s+(?=[^\s،]{3,})/)
            .map((part) => part.trim())
            .filter(Boolean);
        if (parts.length > 1) {
            return parts;
        }
    }
    return [raw];
}

function AnswerValue({ field }: { field: BaselineField }) {
    const text = String(field.value ?? "").trim();
    const parts = splitValues(field);
    const ltr = typeof field.value === "number" || /^[\d+.\-]+$/.test(text) || /^0\d+$/.test(text);

    if (field.kind === "boolean" || (parts.length === 1 && (isAffirmative(text) || isNegative(text)))) {
        const yes = isAffirmative(text);
        return (
            <span className={`baseline-survey__badge${yes ? " baseline-survey__badge--yes" : " baseline-survey__badge--no"}`}>
                <i className={`bi ${yes ? "bi-check-circle-fill" : "bi-dash-circle"}`} aria-hidden="true" />
                {text}
            </span>
        );
    }

    if (parts.length > 1) {
        return (
            <ul className="baseline-survey__chips">
                {parts.map((part) => (
                    <li key={`${field.key}-${part}`}>
                        <span>{part}</span>
                    </li>
                ))}
            </ul>
        );
    }

    return <bdi dir={ltr ? "ltr" : "auto"}>{text}</bdi>;
}

function BaselineFieldRow({ field }: { field: BaselineField }) {
    const [expanded, setExpanded] = useState(false);
    const longLabel = field.label.length > 90;
    const parts = splitValues(field);
    const wide =
        field.kind === "multi_choice" ||
        parts.length > 1 ||
        String(field.value).length > 48 ||
        longLabel;

    return (
        <div className={`baseline-survey__item${wide ? " baseline-survey__item--wide" : ""}`}>
            <dt>
                <span className={longLabel && !expanded ? "baseline-survey__label-clamp" : undefined} title={field.label}>
                    {field.label}
                </span>
                {longLabel ? (
                    <button
                        type="button"
                        className="baseline-survey__label-more"
                        onClick={() => setExpanded((v) => !v)}
                        aria-expanded={expanded}
                    >
                        {expanded ? "أقل" : "النص كاملاً"}
                    </button>
                ) : null}
            </dt>
            <dd>
                <AnswerValue field={field} />
            </dd>
        </div>
    );
}

function SectionBlock({
    section,
    open,
    onToggle,
}: {
    section: BaselineSection;
    open: boolean;
    onToggle: () => void;
}) {
    const panelId = useId();
    const buttonId = useId();

    return (
        <section className={`baseline-survey__section${open ? " is-open" : ""}`}>
            <h3 className="baseline-survey__section-head">
                <button
                    type="button"
                    id={buttonId}
                    className="baseline-survey__section-toggle"
                    aria-expanded={open}
                    aria-controls={panelId}
                    onClick={onToggle}
                >
                    <span className="baseline-survey__section-title">
                        <i className="bi bi-folder2-open" aria-hidden="true" />
                        {section.label}
                    </span>
                    <span className="baseline-survey__section-meta">
                        <span className="baseline-survey__count">{section.fields.length}</span>
                        <i className={`bi ${open ? "bi-chevron-up" : "bi-chevron-down"}`} aria-hidden="true" />
                    </span>
                </button>
            </h3>
            <div id={panelId} role="region" aria-labelledby={buttonId} hidden={!open} className="baseline-survey__section-body">
                <dl className="baseline-survey__grid">
                    {section.fields.map((field) => (
                        <BaselineFieldRow key={field.key} field={field} />
                    ))}
                </dl>
            </div>
        </section>
    );
}

export function BaselineSurveyPanel({ baseline }: { baseline: BaselinePayload }) {
    const t = useT();
    const [openKeys, setOpenKeys] = useState<string[]>(() =>
        baseline.sections[0] ? [baseline.sections[0].key] : [],
    );

    const highlight = useMemo(() => baseline.highlight ?? [], [baseline.highlight]);

    if (!baseline.has_profile) {
        return (
            <SectionCard title={t("smes.show.baseline_empty_title", undefined, "لا توجد بيانات Baseline")}>
                <p className="enterprise-profile__quiet mb-0">
                    {t(
                        "smes.show.baseline_empty",
                        undefined,
                        "لم يُستورد استبيان Baseline لهذه المنشأة بعد.",
                    )}
                </p>
            </SectionCard>
        );
    }

    if (baseline.sections.length === 0) {
        return (
            <SectionCard title={t("smes.show.baseline_hidden_title", undefined, "لا حقول ظاهرة")}>
                <p className="enterprise-profile__quiet mb-0">
                    {t(
                        "smes.show.baseline_hidden",
                        undefined,
                        "يوجد بروفايل مستورد، لكن الأدمن أخفى كل الأقسام الظاهرة حالياً.",
                    )}
                </p>
            </SectionCard>
        );
    }

    return (
        <div className="baseline-survey">
            <div className="baseline-survey__meta">
                {baseline.external_mse_id ? (
                    <div className="baseline-survey__chip">
                        <span>{t("smes.show.baseline_mse_id", undefined, "رقم المنشأة")}</span>
                        <bdi dir="ltr">{baseline.external_mse_id}</bdi>
                    </div>
                ) : null}
                {baseline.imported_at ? (
                    <div className="baseline-survey__chip">
                        <span>{t("smes.show.baseline_imported_at", undefined, "آخر استيراد")}</span>
                        <bdi dir="ltr">{baseline.imported_at}</bdi>
                    </div>
                ) : null}
                <div className="baseline-survey__chip baseline-survey__chip--soft">
                    <span>{t("smes.show.baseline_sections", undefined, "الأقسام")}</span>
                    <strong>{baseline.sections.length}</strong>
                </div>
            </div>

            {highlight.length > 0 ? (
                <div className="baseline-survey__highlight" aria-label={t("smes.show.baseline_summary", undefined, "ملخص سريع")}>
                    {highlight.map((item) => (
                        <div key={item.key} className="baseline-survey__highlight-item">
                            <span>{item.label}</span>
                            <strong>
                                <bdi dir="auto">{item.value}</bdi>
                            </strong>
                        </div>
                    ))}
                </div>
            ) : null}

            <div className="baseline-survey__toc" aria-label={t("smes.show.baseline_toc", undefined, "أقسام الاستبيان")}>
                {baseline.sections.map((section) => {
                    const active = openKeys.includes(section.key);
                    return (
                        <button
                            key={`toc-${section.key}`}
                            type="button"
                            className={`baseline-survey__toc-item${active ? " is-active" : ""}`}
                            onClick={() =>
                                setOpenKeys((keys) =>
                                    keys.includes(section.key) ? keys : [...keys, section.key],
                                )
                            }
                        >
                            {section.label}
                        </button>
                    );
                })}
            </div>

            <div className="baseline-survey__sections">
                {baseline.sections.map((section) => (
                    <SectionBlock
                        key={section.key}
                        section={section}
                        open={openKeys.includes(section.key)}
                        onToggle={() =>
                            setOpenKeys((keys) =>
                                keys.includes(section.key)
                                    ? keys.filter((k) => k !== section.key)
                                    : [...keys, section.key],
                            )
                        }
                    />
                ))}
            </div>
        </div>
    );
}

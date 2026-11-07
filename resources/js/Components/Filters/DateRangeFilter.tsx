import { useT } from "../../Hooks/useT";

export type DateRange = { from: string; to: string };

/** Native date inputs (ISO yyyy-mm-dd) so the server receives unambiguous values; dir=ltr keeps digits readable in RTL. */
export function DateRangeFilter({ id, label, value, onChange }: {
    id: string; label?: string; value: DateRange; onChange: (v: DateRange) => void;
}) {
    const t = useT();
    const periodLabel = label ?? t("filters.period", undefined, "الفترة");
    return (
        <fieldset>
            <legend className="form-label mb-1 fw-semibold fs-6">{periodLabel}</legend>
            <div className="d-flex gap-2">
                <div className="flex-fill">
                    <label htmlFor={`${id}-from`} className="visually-hidden">{t("common.from", undefined, "من")}</label>
                    <input id={`${id}-from`} type="date" dir="ltr" className="form-control" style={{ minHeight: 48, fontSize: 16 }}
                        value={value.from} max={value.to || undefined} onChange={(e) => onChange({ ...value, from: e.target.value })} aria-label={t("filters.date_from", undefined, "من تاريخ")} />
                </div>
                <div className="flex-fill">
                    <label htmlFor={`${id}-to`} className="visually-hidden">{t("common.to", undefined, "إلى")}</label>
                    <input id={`${id}-to`} type="date" dir="ltr" className="form-control" style={{ minHeight: 48, fontSize: 16 }}
                        value={value.to} min={value.from || undefined} onChange={(e) => onChange({ ...value, to: e.target.value })} aria-label={t("filters.date_to", undefined, "إلى تاريخ")} />
                </div>
            </div>
        </fieldset>
    );
}
import { useT } from "../../Hooks/useT";

export type Option = { value: string; label: string };

export function SelectFilter({ id, label, value, options, onChange, allLabel }: {
    id: string; label: string; value: string; options: Option[]; onChange: (v: string) => void; allLabel?: string;
}) {
    const t = useT();
    const all = allLabel ?? t("common.all", undefined, "الكل");
    return (
        <div>
            <label htmlFor={id} className="form-label mb-1 fw-semibold">{label}</label>
            <select id={id} className="form-select" style={{ minHeight: 48, fontSize: 16 }} value={value} onChange={(e) => onChange(e.target.value)}>
                <option value="">{all}</option>
                {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
        </div>
    );
}

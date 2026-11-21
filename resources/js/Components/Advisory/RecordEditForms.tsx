import { useForm } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";
import { SectionCard } from "../Common/SectionCard";
import { TextInput } from "../Forms/TextInput";

export type EditableActivity = {
    id: number;
    activity_date: string | null;
    notes: string | null;
    target_date: string | null;
};

export type EditableFollowUp = {
    id: number;
    description: string;
    target_date: string | null;
};

/** Edit an advisory activity. The responsible person is left unchanged (not sent). */
export function ActivityEditForm({ activity, onClose }: { activity: EditableActivity; onClose: () => void }) {
    const t = useT();
    const form = useForm({
        activity_date: activity.activity_date ?? "",
        notes: activity.notes ?? "",
        target_date: activity.target_date ?? "",
    });

    return (
        <SectionCard title={t("advisory.edit_activity", undefined, "تعديل النشاط")}>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/advisory-activities/${activity.id}`, {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }}
            >
                <TextInput
                    id={`edit_activity_date_${activity.id}`}
                    label={t("advisory.activity_date", undefined, "تاريخ النشاط")}
                    name="activity_date"
                    type="date"
                    value={form.data.activity_date}
                    onChange={(value) => form.setData("activity_date", value)}
                    error={form.errors.activity_date}
                />
                <div className="mb-3">
                    <label htmlFor={`edit_notes_${activity.id}`} className="form-label mb-1 fw-semibold">
                        {t("common.notes", undefined, "ملاحظات")}
                    </label>
                    <textarea
                        id={`edit_notes_${activity.id}`}
                        name="notes"
                        className="form-control"
                        style={{ minHeight: "96px", fontSize: "16px" }}
                        value={form.data.notes}
                        onChange={(event) => form.setData("notes", event.target.value)}
                    />
                    {form.errors.notes ? <div className="text-danger small mt-1">{form.errors.notes}</div> : null}
                </div>
                <TextInput
                    id={`edit_activity_target_${activity.id}`}
                    label={t("advisory.target_date_optional", undefined, "التاريخ المستهدف (اختياري)")}
                    name="target_date"
                    type="date"
                    value={form.data.target_date}
                    onChange={(value) => form.setData("target_date", value)}
                    error={form.errors.target_date}
                />
                <div className="d-flex flex-wrap gap-2">
                    <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                        {t("advisory.save_changes", undefined, "حفظ التعديلات")}
                    </button>
                    <button type="button" className="btn btn-outline-secondary" style={{ minHeight: "48px" }} onClick={onClose}>
                        {t("common.cancel", undefined, "إلغاء")}
                    </button>
                </div>
            </form>
        </SectionCard>
    );
}

/** Edit an open follow-up action. */
export function FollowUpEditForm({ followUp, onClose }: { followUp: EditableFollowUp; onClose: () => void }) {
    const t = useT();
    const form = useForm({
        description: followUp.description,
        target_date: followUp.target_date ?? "",
    });

    return (
        <SectionCard title={t("advisory.edit_follow_up", undefined, "تعديل إجراء المتابعة")}>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/follow-ups/${followUp.id}`, {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }}
            >
                <TextInput
                    id={`edit_follow_up_description_${followUp.id}`}
                    label={t("advisory.action", undefined, "الإجراء")}
                    name="description"
                    value={form.data.description}
                    onChange={(value) => form.setData("description", value)}
                    error={form.errors.description}
                />
                <TextInput
                    id={`edit_follow_up_target_${followUp.id}`}
                    label={t("advisory.target_date_optional", undefined, "التاريخ المستهدف (اختياري)")}
                    name="target_date"
                    type="date"
                    value={form.data.target_date}
                    onChange={(value) => form.setData("target_date", value)}
                    error={form.errors.target_date}
                />
                <div className="d-flex flex-wrap gap-2">
                    <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                        {t("advisory.save_changes", undefined, "حفظ التعديلات")}
                    </button>
                    <button type="button" className="btn btn-outline-secondary" style={{ minHeight: "48px" }} onClick={onClose}>
                        {t("common.cancel", undefined, "إلغاء")}
                    </button>
                </div>
            </form>
        </SectionCard>
    );
}

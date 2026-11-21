import { useForm } from "@inertiajs/react";
import { useT } from "../../Hooks/useT";
import { SectionCard } from "../Common/SectionCard";
import { TextInput } from "../Forms/TextInput";

export type EditableUser = { id: number; name: string; email: string };

/** Admin correction of another user's name and email. Role and activation have their own controls. */
export function UserProfileEditForm({ user, onClose }: { user: EditableUser; onClose: () => void }) {
    const t = useT();
    const form = useForm({ name: user.name, email: user.email });

    return (
        <SectionCard title={t("users.edit_profile", undefined, "تعديل الاسم والبريد")}>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/users/${user.id}/profile`, { preserveScroll: true, onSuccess: onClose });
                }}
                style={{ maxWidth: "650px" }}
            >
                <TextInput
                    id={`profile_name_${user.id}`}
                    label={t("users.profile_name", undefined, "الاسم")}
                    name="name"
                    value={form.data.name}
                    onChange={(value) => form.setData("name", value)}
                    error={form.errors.name}
                    autoComplete="off"
                    required
                />
                <TextInput
                    id={`profile_email_${user.id}`}
                    label={t("users.profile_email", undefined, "البريد الإلكتروني")}
                    name="email"
                    type="email"
                    value={form.data.email}
                    onChange={(value) => form.setData("email", value)}
                    error={form.errors.email}
                    autoComplete="off"
                    required
                />
                <div className="d-flex flex-wrap gap-2">
                    <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                        {t("users.profile_save", undefined, "حفظ البيانات")}
                    </button>
                    <button type="button" className="btn btn-outline-secondary" style={{ minHeight: "48px" }} onClick={onClose}>
                        {t("common.cancel", undefined, "إلغاء")}
                    </button>
                </div>
            </form>
        </SectionCard>
    );
}

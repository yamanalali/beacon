import { Link, useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../../Components/Forms/TextInput";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import AppLayout from "../../../Layouts/AppLayout";
import { useT } from "../../../Hooks/useT";

type RoleOption = {
    value: string;
    label: string;
};

type CreateUserProps = {
    roles: RoleOption[];
};

export default function Create({ roles }: CreateUserProps) {
    const t = useT();
    const form = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        role: roles[0]?.value ?? "",
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post("/users");
    }

    return (
        <AppLayout title={t("admin.users.create_title", undefined, "إضافة حساب")}>
            <div className="app-page">
                <PageHeader title={t("admin.users.create_title", undefined, "إضافة حساب")} />
                <SectionCard title={t("admin.users.account_data", undefined, "بيانات الحساب")}>
                    <form onSubmit={submit} noValidate style={{ maxWidth: "40rem" }}>
                        <TextInput
                            id="name"
                            name="name"
                            label={t("account.name", undefined, "الاسم")}
                            value={form.data.name}
                            error={form.errors.name}
                            onChange={(value) => form.setData("name", value)}
                        />
                        <TextInput
                            id="email"
                            name="email"
                            type="email"
                            label={t("account.email", undefined, "البريد الإلكتروني")}
                            value={form.data.email}
                            error={form.errors.email}
                            onChange={(value) => form.setData("email", value)}
                        />
                        <TextInput
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            label={t("admin.users.password", undefined, "كلمة المرور")}
                            value={form.data.password}
                            error={form.errors.password}
                            onChange={(value) => form.setData("password", value)}
                        />
                        <TextInput
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            label={t("admin.users.password_confirm", undefined, "تأكيد كلمة المرور")}
                            value={form.data.password_confirmation}
                            error={form.errors.password_confirmation}
                            onChange={(value) => form.setData("password_confirmation", value)}
                        />
                        <div className="mb-3">
                            <label htmlFor="role" className="form-label fw-semibold">
                                {t("account.role", undefined, "الدور")}
                            </label>
                            <select
                                id="role"
                                name="role"
                                className={`form-select${form.errors.role ? " is-invalid" : ""}`}
                                style={{ minHeight: "48px", fontSize: "16px" }}
                                value={form.data.role}
                                onChange={(event) => form.setData("role", event.target.value)}
                            >
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.role ? (
                                <div className="invalid-feedback d-block" role="alert">
                                    {form.errors.role}
                                </div>
                            ) : null}
                        </div>
                        <div className="d-flex gap-2">
                            <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                                {t("admin.users.save_account", undefined, "حفظ الحساب")}
                            </button>
                            <Link href="/users" className="btn btn-outline-secondary" style={{ minHeight: "48px" }}>
                                {t("common.cancel", undefined, "إلغاء")}
                            </Link>
                        </div>
                    </form>
                </SectionCard>
            </div>
        </AppLayout>
    );
}

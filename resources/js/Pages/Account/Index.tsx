import { useForm, usePage } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import AppLayout from "../../Layouts/AppLayout";
import { useT } from "../../Hooks/useT";

type AccountPageProps = {
    account: {
        name: string;
        email: string;
        role_label: string;
    };
};

export default function Index({ account }: AccountPageProps) {
    const t = useT();
    const { flash } = usePage().props as { flash?: { status?: string } };
    const form = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put("/account/password");
    }

    return (
        <AppLayout title={t("account.title", undefined, "حسابي")}>
            <div className="app-page">
                <PageHeader
                    title={t("account.title", undefined, "حسابي")}
                    description={t("account.description", undefined, "بيانات الدخول الخاصة بهذا الحساب.")}
                />
                <div className="row g-3">
                    <div className="col-12 col-lg-6">
                        <SectionCard title={t("account.details_section", undefined, "البيانات")}>
                            <dl className="mb-0">
                                <dt>{t("account.name", undefined, "الاسم")}</dt>
                                <dd>{account.name}</dd>
                                <dt>{t("account.email", undefined, "البريد الإلكتروني")}</dt>
                                <dd>{account.email}</dd>
                                <dt>{t("account.role", undefined, "الدور")}</dt>
                                <dd className="mb-0">{account.role_label}</dd>
                            </dl>
                        </SectionCard>
                    </div>
                    <div className="col-12 col-lg-6">
                        <SectionCard title={t("account.password", undefined, "تغيير كلمة المرور")}>
                            {flash?.status ? (
                                <p
                                    className="alert alert-success"
                                    role="status"
                                >
                                    {flash.status}
                                </p>
                            ) : null}
                            <form onSubmit={submit} noValidate>
                                <TextInput
                                    id="current_password"
                                    name="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    label={t("account.current_password", undefined, "كلمة المرور الحالية")}
                                    value={form.data.current_password}
                                    error={form.errors.current_password}
                                    onChange={(value) =>
                                        form.setData("current_password", value)
                                    }
                                />
                                <TextInput
                                    id="password"
                                    name="password"
                                    type="password"
                                    autoComplete="new-password"
                                    label={t("auth.reset.password", undefined, "كلمة المرور الجديدة")}
                                    value={form.data.password}
                                    error={form.errors.password}
                                    onChange={(value) =>
                                        form.setData("password", value)
                                    }
                                />
                                <TextInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    label={t("auth.reset.confirm", undefined, "تأكيد كلمة المرور")}
                                    value={form.data.password_confirmation}
                                    error={form.errors.password_confirmation}
                                    onChange={(value) =>
                                        form.setData(
                                            "password_confirmation",
                                            value,
                                        )
                                    }
                                />
                                <button
                                    type="submit"
                                    className="btn btn-primary"
                                    style={{ minHeight: "48px" }}
                                    disabled={form.processing}
                                >
                                    {t("account.save_password", undefined, "حفظ كلمة المرور")}
                                </button>
                            </form>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

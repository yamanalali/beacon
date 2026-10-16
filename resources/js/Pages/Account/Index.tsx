import { useForm, usePage } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import AppLayout from "../../Layouts/AppLayout";

type AccountPageProps = {
    account: {
        name: string;
        email: string;
        role_label: string;
    };
};

export default function Index({ account }: AccountPageProps) {
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
        <AppLayout title="حسابي">
            <div className="app-page">
                <PageHeader title="حسابي" description="بيانات الدخول الخاصة بهذا الحساب." />
                <div className="row g-3">
                    <div className="col-12 col-lg-6">
                        <SectionCard title="البيانات">
                            <dl className="mb-0">
                                <dt>الاسم</dt>
                                <dd>{account.name}</dd>
                                <dt>البريد الإلكتروني</dt>
                                <dd>{account.email}</dd>
                                <dt>الدور</dt>
                                <dd className="mb-0">{account.role_label}</dd>
                            </dl>
                        </SectionCard>
                    </div>
                    <div className="col-12 col-lg-6">
                        <SectionCard title="تغيير كلمة المرور">
                            {flash?.status ? (
                                <p className="alert alert-success" role="status">
                                    {flash.status}
                                </p>
                            ) : null}
                            <form onSubmit={submit} noValidate>
                                <TextInput
                                    id="current_password"
                                    name="current_password"
                                    type="password"
                                    label="كلمة المرور الحالية"
                                    value={form.data.current_password}
                                    error={form.errors.current_password}
                                    onChange={(value) => form.setData("current_password", value)}
                                />
                                <TextInput
                                    id="password"
                                    name="password"
                                    type="password"
                                    label="كلمة المرور الجديدة"
                                    value={form.data.password}
                                    error={form.errors.password}
                                    onChange={(value) => form.setData("password", value)}
                                />
                                <TextInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    label="تأكيد كلمة المرور"
                                    value={form.data.password_confirmation}
                                    error={form.errors.password_confirmation}
                                    onChange={(value) => form.setData("password_confirmation", value)}
                                />
                                <button type="submit" className="btn btn-primary" style={{ minHeight: "48px" }} disabled={form.processing}>
                                    حفظ كلمة المرور
                                </button>
                            </form>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

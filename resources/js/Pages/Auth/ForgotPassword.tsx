import { Link, useForm, usePage } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { useT } from "../../Hooks/useT";
import { GuestAuthLayout } from "./GuestAuthLayout";

export default function ForgotPassword() {
    const { flash } = usePage().props as { flash?: { status?: string } };
    const t = useT();
    const form = useForm({
        email: "",
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post("/forgot-password");
    }

    return (
        <GuestAuthLayout title={t("auth.forgot.title")}>
            <form onSubmit={submit} noValidate>
                {flash?.status ? (
                    <p className="alert alert-success" role="status">
                        {flash.status}
                    </p>
                ) : null}
                <TextInput
                    id="email"
                    name="email"
                    type="email"
                    label={t("auth.login.email")}
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(value) => form.setData("email", value)}
                />
                <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing}>
                    {t("auth.forgot.submit")}
                </button>
                <p className="mt-3 mb-0">
                    <Link href="/login">{t("auth.forgot.back_login")}</Link>
                </p>
            </form>
        </GuestAuthLayout>
    );
}

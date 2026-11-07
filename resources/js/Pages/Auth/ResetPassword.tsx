import { useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { useT } from "../../Hooks/useT";
import { GuestAuthLayout } from "./GuestAuthLayout";

type ResetPasswordProps = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const t = useT();
    const form = useForm({
        token,
        email,
        password: "",
        password_confirmation: "",
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post("/reset-password");
    }

    return (
        <GuestAuthLayout title={t("auth.reset.title")}>
            <form onSubmit={submit} noValidate>
                <TextInput
                    id="email"
                    name="email"
                    type="email"
                    label={t("auth.login.email")}
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(value) => form.setData("email", value)}
                />
                <TextInput
                    id="password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    label={t("auth.reset.password")}
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(value) => form.setData("password", value)}
                />
                <TextInput
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    label={t("auth.reset.confirm")}
                    value={form.data.password_confirmation}
                    error={form.errors.password_confirmation}
                    onChange={(value) =>
                        form.setData("password_confirmation", value)
                    }
                />
                <button
                    type="submit"
                    className="btn btn-primary w-100"
                    style={{ minHeight: "48px" }}
                    disabled={form.processing}
                >
                    {t("auth.reset.submit")}
                </button>
            </form>
        </GuestAuthLayout>
    );
}

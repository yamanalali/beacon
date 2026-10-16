import { useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { GuestAuthLayout } from "./GuestAuthLayout";

type ResetPasswordProps = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: ResetPasswordProps) {
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
        <GuestAuthLayout title="تعيين كلمة مرور جديدة">
            <form onSubmit={submit} noValidate>
                <TextInput
                    id="email"
                    name="email"
                    type="email"
                    label="البريد الإلكتروني"
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(value) => form.setData("email", value)}
                />
                <TextInput
                    id="password"
                    name="password"
                    type="password"
                    label="كلمة المرور"
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
                <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing}>
                    حفظ كلمة المرور
                </button>
            </form>
        </GuestAuthLayout>
    );
}

import { Link, useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { GuestAuthLayout } from "./GuestAuthLayout";

export default function Login() {
    const form = useForm({
        email: "",
        password: "",
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post("/login");
    }

    return (
        <GuestAuthLayout title="تسجيل الدخول">
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
                <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing}>
                    تسجيل الدخول
                </button>
                <p className="mt-3 mb-0">
                    <Link href="/forgot-password">نسيت كلمة المرور</Link>
                </p>
            </form>
        </GuestAuthLayout>
    );
}

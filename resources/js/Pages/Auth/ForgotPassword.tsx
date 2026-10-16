import { Link, useForm, usePage } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { GuestAuthLayout } from "./GuestAuthLayout";

export default function ForgotPassword() {
    const { flash } = usePage().props as { flash?: { status?: string } };
    const form = useForm({
        email: "",
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post("/forgot-password");
    }

    return (
        <GuestAuthLayout title="نسيت كلمة المرور">
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
                    label="البريد الإلكتروني"
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(value) => form.setData("email", value)}
                />
                <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing}>
                    إرسال رابط إعادة التعيين
                </button>
                <p className="mt-3 mb-0">
                    <Link href="/login">العودة لتسجيل الدخول</Link>
                </p>
            </form>
        </GuestAuthLayout>
    );
}

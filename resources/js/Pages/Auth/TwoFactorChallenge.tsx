import { useForm } from "@inertiajs/react";
import { FormEvent, useState } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { GuestAuthLayout } from "./GuestAuthLayout";

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const form = useForm({ code: "", recovery_code: "" });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => (useRecovery ? { recovery_code: data.recovery_code } : { code: data.code }));
        form.post("/two-factor-challenge");
    }

    return (
        <GuestAuthLayout title="التحقق بخطوتين">
            <form onSubmit={submit} noValidate>
                {useRecovery ? (
                    <TextInput
                        id="recovery_code"
                        name="recovery_code"
                        label="رمز الاسترداد"
                        value={form.data.recovery_code}
                        error={form.errors.recovery_code}
                        onChange={(value) => form.setData("recovery_code", value)}
                    />
                ) : (
                    <TextInput
                        id="code"
                        name="code"
                        label="الرمز من تطبيق المصادقة"
                        value={form.data.code}
                        error={form.errors.code}
                        onChange={(value) => form.setData("code", value)}
                    />
                )}
                <button type="submit" className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing}>
                    تحقق
                </button>
                <p className="mt-3 mb-0">
                    <button type="button" className="btn btn-link p-0" onClick={() => setUseRecovery(!useRecovery)}>
                        {useRecovery ? "استخدام رمز التطبيق" : "استخدام رمز استرداد"}
                    </button>
                </p>
            </form>
        </GuestAuthLayout>
    );
}

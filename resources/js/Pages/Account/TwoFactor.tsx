import { Head, useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";

type Props = {
    mfa: {
        confirmed: boolean;
        pending: boolean;
        qr_svg: string | null;
        setup_key: string | null;
        recovery_codes_remaining: number;
    };
    recovery_codes?: string[] | null;
};

export default function TwoFactor({ mfa, recovery_codes }: Props) {
    const enable = useForm({});
    const confirm = useForm({ code: "" });
    const secured = useForm({ current_password: "" });

    function submitConfirm(event: FormEvent) {
        event.preventDefault();
        confirm.post("/account/two-factor/confirm");
    }

    return (
        <div className="container py-4" dir="rtl" lang="ar" style={{ maxWidth: "40rem" }}>
            <Head title="التحقق بخطوتين" />
            <h1 className="h4 mb-3">التحقق بخطوتين</h1>

            {recovery_codes && recovery_codes.length > 0 && (
                <div className="alert alert-warning">
                    <p className="fw-semibold mb-2">رموز الاسترداد: احفظها بمكان آمن، ولن تظهر مرة أخرى. كل رمز يعمل مرة واحدة.</p>
                    <ul className="mb-0" dir="ltr" style={{ fontFamily: "monospace" }}>
                        {recovery_codes.map((code) => (
                            <li key={code}>{code}</li>
                        ))}
                    </ul>
                </div>
            )}

            {mfa.confirmed ? (
                <section>
                    <p>التحقق بخطوتين مفعّل. رموز الاسترداد المتبقية: {mfa.recovery_codes_remaining}</p>
                    <TextInput
                        id="current_password"
                        name="current_password"
                        type="password"
                        label="كلمة المرور الحالية"
                        value={secured.data.current_password}
                        error={secured.errors.current_password}
                        onChange={(value) => secured.setData("current_password", value)}
                    />
                    <div className="d-flex gap-2">
                        <button type="button" className="btn btn-outline-primary" disabled={secured.processing} onClick={() => secured.post("/account/two-factor/recovery-codes")}>
                            توليد رموز استرداد جديدة
                        </button>
                        <button type="button" className="btn btn-outline-danger" disabled={secured.processing} onClick={() => secured.delete("/account/two-factor")}>
                            إيقاف التحقق بخطوتين
                        </button>
                    </div>
                </section>
            ) : mfa.pending ? (
                <section>
                    <p>امسح الرمز بتطبيق المصادقة، أو أدخل المفتاح يدوياً، ثم اكتب الرمز المكوّن من 6 أرقام.</p>
                    {mfa.qr_svg && <div className="mb-3" dangerouslySetInnerHTML={{ __html: mfa.qr_svg }} />}
                    <p dir="ltr" style={{ fontFamily: "monospace", wordBreak: "break-all" }}>{mfa.setup_key}</p>
                    <form onSubmit={submitConfirm} noValidate>
                        <TextInput
                            id="code"
                            name="code"
                            label="رمز التحقق"
                            value={confirm.data.code}
                            error={confirm.errors.code}
                            onChange={(value) => confirm.setData("code", value)}
                        />
                        <button type="submit" className="btn btn-primary" disabled={confirm.processing}>
                            تأكيد التفعيل
                        </button>
                    </form>
                </section>
            ) : (
                <section>
                    <p>أضف طبقة حماية لحساب الإدارة باستخدام تطبيق مصادقة (TOTP).</p>
                    <button type="button" className="btn btn-primary" disabled={enable.processing} onClick={() => enable.post("/account/two-factor")}>
                        بدء التفعيل
                    </button>
                </section>
            )}
        </div>
    );
}

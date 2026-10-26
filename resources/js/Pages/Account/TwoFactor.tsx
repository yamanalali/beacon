import { useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
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
    const enable = useForm({ current_password: "" });
    const confirm = useForm({ code: "" });
    const secured = useForm({ current_password: "" });

    function submitConfirm(event: FormEvent) {
        event.preventDefault();
        confirm.post("/account/two-factor/confirm", { onFinish: () => confirm.reset("code") });
    }

    return (
        <AppLayout title="التحقق بخطوتين">
            <div className="app-page">
                <PageHeader
                    title="التحقق بخطوتين"
                    description="إدارة تطبيق المصادقة ورموز الاسترداد الخاصة بحسابك."
                />
                <div className="row g-4">
                    <div className="col-12 col-lg-8">
                        <SectionCard title="إعدادات التحقق">
                            {recovery_codes && recovery_codes.length > 0 && (
                                <div className="alert alert-warning">
                                    <p className="fw-semibold mb-2">
                                        رموز الاسترداد: احفظها بمكان آمن، ولن
                                        تظهر مرة أخرى. كل رمز يعمل مرة واحدة.
                                    </p>
                                    <ul
                                        className="mb-0"
                                        dir="ltr"
                                        style={{ fontFamily: "monospace" }}
                                    >
                                        {recovery_codes.map((code) => (
                                            <li key={code}>{code}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {mfa.confirmed ? (
                                <section>
                                    <p>
                                        التحقق بخطوتين مفعّل. رموز الاسترداد
                                        المتبقية: {mfa.recovery_codes_remaining}
                                    </p>
                                    <TextInput
                                        id="current_password"
                                        name="current_password"
                                        type="password"
                                        autoComplete="current-password"
                                        label="كلمة المرور الحالية"
                                        value={secured.data.current_password}
                                        error={secured.errors.current_password}
                                        onChange={(value) =>
                                            secured.setData(
                                                "current_password",
                                                value,
                                            )
                                        }
                                    />
                                    <div className="d-flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            className="btn btn-outline-primary"
                                            disabled={secured.processing}
                                            onClick={() =>
                                                secured.post(
                                                    "/account/two-factor/recovery-codes",
                                                    { onFinish: () => secured.reset("current_password") },
                                                )
                                            }
                                        >
                                            توليد رموز استرداد جديدة
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-outline-danger"
                                            disabled={secured.processing}
                                            onClick={() =>
                                                secured.delete(
                                                    "/account/two-factor",
                                                    { onFinish: () => secured.reset("current_password") },
                                                )
                                            }
                                        >
                                            إيقاف التحقق بخطوتين
                                        </button>
                                    </div>
                                </section>
                            ) : mfa.pending ? (
                                <section>
                                    <p>
                                        امسح الرمز بتطبيق المصادقة، أو أدخل
                                        المفتاح يدوياً، ثم اكتب الرمز المكوّن من
                                        6 أرقام.
                                    </p>
                                    {mfa.qr_svg && (
                                        <div
                                            className="app-mfa-qr mb-3"
                                            dangerouslySetInnerHTML={{
                                                __html: mfa.qr_svg,
                                            }}
                                        />
                                    )}
                                    <p
                                        dir="ltr"
                                        style={{
                                            fontFamily: "monospace",
                                            wordBreak: "break-all",
                                        }}
                                    >
                                        {mfa.setup_key}
                                    </p>
                                    <form onSubmit={submitConfirm} noValidate>
                                        <TextInput
                                            id="code"
                                            name="code"
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            label="رمز التحقق"
                                            value={confirm.data.code}
                                            error={confirm.errors.code}
                                            onChange={(value) =>
                                                confirm.setData("code", value)
                                            }
                                        />
                                        <button
                                            type="submit"
                                            className="btn btn-primary"
                                            disabled={confirm.processing}
                                        >
                                            تأكيد التفعيل
                                        </button>
                                    </form>
                                </section>
                            ) : (
                                <section>
                                    <p>
                                        أضف طبقة حماية لحساب الإدارة باستخدام
                                        تطبيق مصادقة (TOTP).
                                    </p>
                                    <TextInput id="enable_current_password" name="current_password" type="password" autoComplete="current-password" label="كلمة المرور الحالية" value={enable.data.current_password} error={enable.errors.current_password} onChange={value => enable.setData("current_password", value)} />
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={enable.processing}
                                        onClick={() =>
                                            enable.post("/account/two-factor", { onFinish: () => enable.reset("current_password") })
                                        }
                                    >
                                        بدء التفعيل
                                    </button>
                                </section>
                            )}
                        </SectionCard>
                    </div>
                    <aside className="col-12 col-lg-4">
                        <div className="app-form-guide">
                            <span className="app-form-guide__icon">
                                <i
                                    className="bi bi-shield-lock"
                                    aria-hidden="true"
                                />
                            </span>
                            <h2>حماية إضافية لحسابك</h2>
                            <div className="my-3">
                                <StatusBadge
                                    label={
                                        mfa.confirmed
                                            ? "التحقق مفعّل"
                                            : mfa.pending
                                              ? "بانتظار تأكيد التفعيل"
                                              : "التحقق غير مفعّل"
                                    }
                                    tone={mfa.confirmed ? "success" : "warning"}
                                />
                            </div>
                            <p>
                                استخدم تطبيق المصادقة للحصول على رمز الدخول،
                                واحتفظ برموز الاسترداد في مكان آمن للوصول إلى
                                حسابك عند الحاجة.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

import { useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { TextInput } from "../../Components/Forms/TextInput";
import { useT } from "../../Hooks/useT";

type Props = {
    mfa: {
        confirmed: boolean;
        pending: boolean;
        qr_svg: string | null;
        setup_key: string | null;
        recovery_codes_remaining: number;
        can_disable?: boolean;
        enrollment_required?: boolean;
    };
    recovery_codes?: string[] | null;
};

export default function TwoFactor({ mfa, recovery_codes }: Props) {
    const t = useT();
    const enable = useForm({ current_password: "" });
    const confirm = useForm({ code: "" });
    const secured = useForm({ current_password: "" });

    function submitConfirm(event: FormEvent) {
        event.preventDefault();
        confirm.post("/account/two-factor/confirm", { onFinish: () => confirm.reset("code") });
    }

    const statusLabel = mfa.confirmed
        ? t("account.two_factor.status.enabled", undefined, "التحقق مفعّل")
        : mfa.pending
          ? t("account.two_factor.status.pending", undefined, "بانتظار تأكيد التفعيل")
          : t("account.two_factor.status.disabled", undefined, "التحقق غير مفعّل");

    return (
        <AppLayout title={t("nav.account.two-factor", undefined, "التحقق بخطوتين")}>
            <div className="app-page">
                <PageHeader
                    title={t("nav.account.two-factor", undefined, "التحقق بخطوتين")}
                    description={t("account.two_factor.description", undefined, "إدارة تطبيق المصادقة ورموز الاسترداد الخاصة بحسابك.")}
                />
                <div className="row g-4">
                    <div className="col-12 col-lg-8">
                        <SectionCard title={t("account.two_factor.settings", undefined, "إعدادات التحقق")}>
                            {recovery_codes && recovery_codes.length > 0 && (
                                <div className="alert alert-warning">
                                    <p className="fw-semibold mb-2">
                                        {t(
                                            "account.two_factor.recovery_codes_warning",
                                            undefined,
                                            "رموز الاسترداد: احفظها بمكان آمن، ولن تظهر مرة أخرى. كل رمز يعمل مرة واحدة.",
                                        )}
                                    </p>
                                    <ul className="mb-0" dir="ltr" style={{ fontFamily: "monospace" }}>
                                        {recovery_codes.map((code) => (
                                            <li key={code}>{code}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {mfa.enrollment_required && !mfa.confirmed && (
                                <div className="alert alert-warning">
                                    {t(
                                        "account.two_factor.enrollment_required",
                                        undefined,
                                        "التحقق بخطوتين إلزامي لهذا الحساب. أكمل الإعداد للمتابعة.",
                                    )}
                                </div>
                            )}

                            {mfa.confirmed ? (
                                <section>
                                    <p>
                                        {t("account.two_factor.enabled_notice", { count: mfa.recovery_codes_remaining }, `التحقق بخطوتين مفعّل. رموز الاسترداد المتبقية: ${mfa.recovery_codes_remaining}`)}
                                    </p>
                                    {!mfa.can_disable && (
                                        <p className="text-muted small">
                                            {t(
                                                "account.two_factor.disable_not_allowed",
                                                undefined,
                                                "الإيقاف غير متاح لأن التحقق بخطوتين إلزامي لهذا الدور.",
                                            )}
                                        </p>
                                    )}
                                    <TextInput
                                        id="current_password"
                                        name="current_password"
                                        type="password"
                                        autoComplete="current-password"
                                        label={t("account.current_password", undefined, "كلمة المرور الحالية")}
                                        value={secured.data.current_password}
                                        error={secured.errors.current_password}
                                        onChange={(value) => secured.setData("current_password", value)}
                                    />
                                    <div className="d-flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            className="btn btn-outline-primary"
                                            disabled={secured.processing}
                                            onClick={() =>
                                                secured.post("/account/two-factor/recovery-codes", {
                                                    onFinish: () => secured.reset("current_password"),
                                                })
                                            }
                                        >
                                            {t("account.two_factor.regenerate_codes", undefined, "توليد رموز استرداد جديدة")}
                                        </button>
                                        {mfa.can_disable !== false && (
                                            <button
                                                type="button"
                                                className="btn btn-outline-danger"
                                                disabled={secured.processing}
                                                onClick={() =>
                                                    secured.delete("/account/two-factor", {
                                                        onFinish: () => secured.reset("current_password"),
                                                    })
                                                }
                                            >
                                                {t("account.two_factor.disable", undefined, "إيقاف التحقق بخطوتين")}
                                            </button>
                                        )}
                                    </div>
                                </section>
                            ) : mfa.pending ? (
                                <section>
                                    <p>
                                        {t(
                                            "account.two_factor.pending_instructions",
                                            undefined,
                                            "امسح الرمز بتطبيق المصادقة، أو أدخل المفتاح يدوياً، ثم اكتب الرمز المكوّن من 6 أرقام.",
                                        )}
                                    </p>
                                    {mfa.qr_svg && (
                                        <div className="app-mfa-qr mb-3" dangerouslySetInnerHTML={{ __html: mfa.qr_svg }} />
                                    )}
                                    <p dir="ltr" style={{ fontFamily: "monospace", wordBreak: "break-all" }}>
                                        {mfa.setup_key}
                                    </p>
                                    <form onSubmit={submitConfirm} noValidate>
                                        <TextInput
                                            id="code"
                                            name="code"
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            label={t("account.two_factor.code", undefined, "رمز التحقق")}
                                            value={confirm.data.code}
                                            error={confirm.errors.code}
                                            onChange={(value) => confirm.setData("code", value)}
                                        />
                                        <button type="submit" className="btn btn-primary" disabled={confirm.processing}>
                                            {t("account.two_factor.confirm_enable", undefined, "تأكيد التفعيل")}
                                        </button>
                                    </form>
                                </section>
                            ) : (
                                <section>
                                    <p>
                                        {t(
                                            "account.two_factor.enable_intro",
                                            undefined,
                                            "أضف طبقة حماية لحساب الإدارة باستخدام تطبيق مصادقة (TOTP).",
                                        )}
                                    </p>
                                    <TextInput
                                        id="enable_current_password"
                                        name="current_password"
                                        type="password"
                                        autoComplete="current-password"
                                        label={t("account.current_password", undefined, "كلمة المرور الحالية")}
                                        value={enable.data.current_password}
                                        error={enable.errors.current_password}
                                        onChange={(value) => enable.setData("current_password", value)}
                                    />
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={enable.processing}
                                        onClick={() =>
                                            enable.post("/account/two-factor", { onFinish: () => enable.reset("current_password") })
                                        }
                                    >
                                        {t("account.two_factor.start_enable", undefined, "بدء التفعيل")}
                                    </button>
                                </section>
                            )}
                        </SectionCard>
                    </div>
                    <aside className="col-12 col-lg-4">
                        <div className="app-form-guide">
                            <span className="app-form-guide__icon">
                                <i className="bi bi-shield-lock" aria-hidden="true" />
                            </span>
                            <h2>{t("account.two_factor.guide_title", undefined, "حماية إضافية لحسابك")}</h2>
                            <div className="my-3">
                                <StatusBadge label={statusLabel} tone={mfa.confirmed ? "success" : "warning"} />
                            </div>
                            <p>
                                {t(
                                    "account.two_factor.guide_body",
                                    undefined,
                                    "استخدم تطبيق المصادقة للحصول على رمز الدخول، واحتفظ برموز الاسترداد في مكان آمن للوصول إلى حسابك عند الحاجة.",
                                )}
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

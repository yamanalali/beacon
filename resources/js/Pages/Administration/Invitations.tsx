import { Link, router, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { useT } from "../../Hooks/useT";

type Invite = { id: number; name: string; email: string; role: string; expires_at: string; accepted_at: string | null; revoked_at: string | null };

export default function Invitations({
    invitations,
    roles,
    enterprises,
    institutions,
}: {
    invitations: Invite[];
    roles: { value: string; label: string }[];
    enterprises: { id: number; business_name: string }[];
    institutions: { id: number; name_ar: string }[];
}) {
    const t = useT();
    const { flash } = usePage().props;
    const form = useForm({ name: "", email: "", role: "sme_user", enterprise_id: "", mfi_institution_id: "" });

    const inviteStatus = (i: Invite) => {
        if (i.accepted_at) return t("admin.invitations.status.accepted", undefined, "تم التفعيل");
        if (i.revoked_at) return t("admin.invitations.status.revoked", undefined, "ملغاة");
        if (new Date(i.expires_at) < new Date()) return t("admin.invitations.status.expired", undefined, "منتهية");
        return t("admin.invitations.status.pending", undefined, "بانتظار التفعيل");
    };

    return (
        <AppLayout title={t("admin.index.setting.invitations", undefined, "دعوات المستخدمين")}>
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader
                    title={t("admin.index.setting.invitations", undefined, "دعوات المستخدمين")}
                    description={t("admin.invitations.description", undefined, "أرسل رابط تفعيل آمن لصاحب الحساب ليختار كلمة مروره بنفسه.")}
                />
                <Link href="/users" className="btn btn-light align-self-start">
                    {t("admin.invitations.manage_users", undefined, "إدارة المستخدمين")}
                </Link>
                {flash?.invitation_link ? (
                    <div className="alert alert-success" role="status">
                        <label htmlFor="invite-link" className="form-label fw-semibold">
                            {t("admin.invitations.link_label", undefined, "رابط الدعوة — يظهر مرة واحدة، شاركه عبر قناتك المعتمدة")}
                        </label>
                        <input id="invite-link" className="form-control" dir="ltr" readOnly value={flash.invitation_link} onFocus={(e) => e.target.select()} />
                        <p className="small mb-0 mt-2">
                            {t(
                                "admin.invitations.link_hint",
                                undefined,
                                "لا يرسل النظام بريداً تلقائياً. الرابط صالح لسبعة أيام ولمرة واحدة.",
                            )}
                        </p>
                    </div>
                ) : null}
                <SectionCard title={t("admin.invitations.new", undefined, "دعوة جديدة")}>
                    <form
                        className="row g-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post("/admin/invitations", { preserveScroll: true, onSuccess: () => form.reset("name", "email") });
                        }}
                    >
                        <div className="col-12 col-md-6">
                            <label className="form-label" htmlFor="invite-name">
                                {t("account.name", undefined, "الاسم")}
                            </label>
                            <input
                                id="invite-name"
                                className="form-control"
                                required
                                value={form.data.name}
                                onChange={(e) => form.setData("name", e.target.value)}
                            />
                        </div>
                        <div className="col-12 col-md-6">
                            <label className="form-label" htmlFor="invite-email">
                                {t("account.email", undefined, "البريد الإلكتروني")}
                            </label>
                            <input
                                id="invite-email"
                                type="email"
                                dir="ltr"
                                className="form-control"
                                required
                                value={form.data.email}
                                onChange={(e) => form.setData("email", e.target.value)}
                            />
                        </div>
                        <div className="col-12 col-md-6">
                            <label className="form-label" htmlFor="invite-role">
                                {t("account.role", undefined, "الدور")}
                            </label>
                            <select
                                id="invite-role"
                                className="form-select"
                                value={form.data.role}
                                onChange={(e) =>
                                    form.setData((d) => ({
                                        ...d,
                                        role: e.target.value,
                                        enterprise_id: "",
                                        mfi_institution_id: "",
                                    }))
                                }
                            >
                                {roles.map((r) => (
                                    <option key={r.value} value={r.value}>
                                        {r.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {["sme_user", "beacon_advisor"].includes(form.data.role) ? (
                            <div className="col-12 col-md-6">
                                <label htmlFor="invite-enterprise" className="form-label">
                                    {t("admin.invitations.enterprise", undefined, "المنشأة")}{" "}
                                    {form.data.role === "sme_user"
                                        ? t("common.required", undefined, "(مطلوبة)")
                                        : t("common.optional", undefined, "(اختيارية)")}
                                </label>
                                <select
                                    id="invite-enterprise"
                                    className="form-select"
                                    required={form.data.role === "sme_user"}
                                    value={form.data.enterprise_id}
                                    onChange={(e) => form.setData("enterprise_id", e.target.value)}
                                >
                                    <option value="">{t("admin.invitations.choose_enterprise", undefined, "اختر المنشأة")}</option>
                                    {enterprises.map((e) => (
                                        <option key={e.id} value={e.id}>
                                            {e.business_name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : null}
                        {form.data.role === "mfi_user" ? (
                            <div className="col-12 col-md-6">
                                <label htmlFor="invite-institution" className="form-label">
                                    {t("admin.invitations.mfi", undefined, "مؤسسة التمويل")}
                                </label>
                                <select
                                    id="invite-institution"
                                    className="form-select"
                                    required
                                    value={form.data.mfi_institution_id}
                                    onChange={(e) => form.setData("mfi_institution_id", e.target.value)}
                                >
                                    <option value="">{t("admin.invitations.choose_mfi", undefined, "اختر المؤسسة")}</option>
                                    {institutions.map((e) => (
                                        <option key={e.id} value={e.id}>
                                            {e.name_ar}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : null}
                        {Object.values(form.errors).map((error, i) => (
                            <p className="text-danger mb-0" role="alert" key={i}>
                                {error}
                            </p>
                        ))}
                        <div>
                            <button className="btn btn-primary" disabled={form.processing}>
                                {t("admin.invitations.create_link", undefined, "إنشاء رابط الدعوة")}
                            </button>
                        </div>
                    </form>
                </SectionCard>
                <SectionCard title={t("admin.invitations.recent", undefined, "آخر الدعوات")}>
                    <ul className="list-group list-group-flush">
                        {invitations.map((i) => (
                            <li key={i.id} className="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2">
                                <span>
                                    <strong>{i.name}</strong>
                                    <bdi className="d-block small">{i.email}</bdi>
                                </span>
                                <span>{inviteStatus(i)}</span>
                                {!i.accepted_at && !i.revoked_at ? (
                                    <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => router.post(`/admin/invitations/${i.id}/revoke`)}>
                                        {t("admin.invitations.revoke", undefined, "إلغاء الدعوة")}
                                    </button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                    {!invitations.length ? (
                        <p className="text-body-secondary mb-0">{t("admin.invitations.empty", undefined, "لا توجد دعوات بعد.")}</p>
                    ) : null}
                </SectionCard>
            </div>
        </AppLayout>
    );
}

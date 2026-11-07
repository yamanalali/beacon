import { Link, router, usePage } from "@inertiajs/react";
import { FormEvent, useMemo, useState } from "react";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { DataTable, type Column, type Paginator as DataPaginator } from "../../../Components/Data/DataTable";
import AppLayout from "../../../Layouts/AppLayout";
import { useT } from "../../../Hooks/useT";

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
};

type UsersIndexProps = {
    users: DataPaginator<UserRow>;
    search: string;
    currentUserId: number;
    mfaNote: string;
    roles: { value: string; label: string }[];
};

export default function Index({ users, search, currentUserId, mfaNote, roles }: UsersIndexProps) {
    const t = useT();
    const { errors } = usePage().props;

    function changeRole(user: UserRow, role: string) {
        if (role === user.role) {
            return;
        }
        const label = roles.find((r) => r.value === role)?.label ?? role;
        if (
            window.confirm(
                t(
                    "admin.users.confirm_role_change",
                    { name: user.name, role: label },
                    `تغيير دور ${user.name} إلى «${label}»؟ سيتم تسجيل خروجه من جميع الأجهزة.`,
                ),
            )
        ) {
            router.put(`/users/${user.id}/role`, { role }, { preserveScroll: true });
        }
    }

    const [term, setTerm] = useState(search);

    function onSearch(event: FormEvent) {
        event.preventDefault();
        router.get("/users", { search: term }, { preserveState: true });
    }

    function confirmDeactivate(user: UserRow) {
        if (window.confirm(t("admin.users.confirm_deactivate", undefined, "هل تريد إيقاف هذا الحساب؟ لن يتمكن المستخدم من تسجيل الدخول."))) {
            router.post(`/users/${user.id}/deactivate`);
        }
    }

    function confirmActivate(user: UserRow) {
        if (window.confirm(t("admin.users.confirm_activate", undefined, "هل تريد إعادة تفعيل هذا الحساب؟"))) {
            router.post(`/users/${user.id}/activate`);
        }
    }

    const columns: Column<UserRow>[] = useMemo(
        () => [
            {
                key: "access",
                header: t("admin.users.access.title", undefined, "الصلاحيات"),
                cell: (user) => (
                    <Link href={`/users/${user.id}/access`} className="btn btn-sm btn-outline-primary">
                        {t("admin.users.access.section", undefined, "الصلاحيات والمسؤوليات")}
                    </Link>
                ),
            },
            { key: "name", header: t("account.name", undefined, "الاسم"), cell: (user) => user.name, primary: true },
            { key: "email", header: t("account.email", undefined, "البريد الإلكتروني"), cell: (user) => <bdi dir="ltr">{user.email}</bdi> },
            {
                key: "role",
                header: t("account.role", undefined, "الدور"),
                cell: (user) =>
                    user.id !== currentUserId ? (
                        <div style={{ maxWidth: "16rem" }}>
                            <label className="visually-hidden" htmlFor={`role-${user.id}`}>
                                {t("account.role", undefined, "الدور")}
                            </label>
                            <select
                                id={`role-${user.id}`}
                                className="form-select"
                                style={{ minHeight: "44px", fontSize: "16px" }}
                                value={user.role}
                                onChange={(event) => changeRole(user, event.target.value)}
                            >
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ) : (
                        user.role_label
                    ),
            },
            {
                key: "is_active",
                header: t("common.status", undefined, "الحالة"),
                cell: (user) => (user.is_active ? t("status.active", undefined, "نشط") : t("admin.users.inactive", undefined, "موقوف")),
            },
            {
                key: "actions",
                header: "",
                hideable: false,
                cell: (user) =>
                    user.id === currentUserId ? null : user.is_active ? (
                        <button type="button" className="btn btn-outline-danger btn-sm" style={{ minHeight: "44px" }} onClick={() => confirmDeactivate(user)}>
                            {t("admin.users.deactivate", undefined, "إيقاف الحساب")}
                        </button>
                    ) : (
                        <button type="button" className="btn btn-outline-success btn-sm" style={{ minHeight: "44px" }} onClick={() => confirmActivate(user)}>
                            {t("admin.users.activate", undefined, "تفعيل الحساب")}
                        </button>
                    ),
            },
        ],
        [t, currentUserId, roles],
    );

    return (
        <AppLayout title={t("nav.admin.users", undefined, "المستخدمون")}>
            <div className="app-page">
                <PageHeader
                    title={t("nav.admin.users", undefined, "المستخدمون")}
                    description={t("admin.users.index_description", undefined, "إنشاء حسابات البرنامج وإيقافها.")}
                />
                <p className="alert alert-warning" role="note">
                    {mfaNote}
                </p>
                <div className="d-flex flex-column flex-md-row gap-2 justify-content-between mb-3">
                    <form className="d-flex gap-2 w-100" onSubmit={onSearch} style={{ maxWidth: "36rem" }}>
                        <label className="visually-hidden" htmlFor="user-search">
                            {t("admin.users.search_label", undefined, "بحث بالاسم أو البريد")}
                        </label>
                        <input
                            id="user-search"
                            className="form-control"
                            style={{ minHeight: "48px" }}
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            placeholder={t("admin.users.search_placeholder", undefined, "الاسم أو البريد الإلكتروني")}
                        />
                        <button type="submit" className="btn btn-outline-secondary" style={{ minHeight: "48px" }}>
                            {t("common.search", undefined, "بحث")}
                        </button>
                    </form>
                    <Link href="/admin/invitations" className="btn btn-outline-primary">
                        {t("admin.users.invite", undefined, "دعوة مستخدم")}
                    </Link>
                    <Link href="/users/create" className="btn btn-primary" style={{ minHeight: "48px" }}>
                        {t("admin.users.create_title", undefined, "إضافة حساب")}
                    </Link>
                </div>
                {errors.role ? (
                    <p className="alert alert-danger" role="alert">
                        {errors.role}
                    </p>
                ) : null}
                <SectionCard title={t("admin.users.accounts_section", undefined, "الحسابات")}>
                    <DataTable
                        caption={t("admin.users.accounts_section", undefined, "الحسابات")}
                        columns={columns}
                        rowKey={(user) => user.id}
                        emptyTitle={t("admin.users.empty", undefined, "لا توجد حسابات مطابقة.")}
                        server={{ paginator: users, url: "/users", query: { search: search || undefined }, only: ["users", "search"] }}
                    />
                </SectionCard>
            </div>
        </AppLayout>
    );
}

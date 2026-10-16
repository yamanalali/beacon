import { Link, router, usePage } from "@inertiajs/react";
import { FormEvent, useState } from "react";
import { PageHeader } from "../../../Components/Common/PageHeader";
import { SectionCard } from "../../../Components/Common/SectionCard";
import { EmptyState } from "../../../Components/Feedback/EmptyState";
import AppLayout from "../../../Layouts/AppLayout";

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
};

type Paginator = {
    data: UserRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

type UsersIndexProps = {
    users: Paginator;
    search: string;
    currentUserId: number;
    mfaNote: string;
    roles: { value: string; label: string }[];
};

export default function Index({ users, search, currentUserId, mfaNote, roles }: UsersIndexProps) {
    const { errors } = usePage().props;

    function changeRole(user: UserRow, role: string) {
        if (role === user.role) {
            return;
        }
        const label = roles.find((r) => r.value === role)?.label ?? role;
        if (window.confirm(`تغيير دور ${user.name} إلى «${label}»؟ سيتم تسجيل خروجه من جميع الأجهزة.`)) {
            router.put(`/users/${user.id}/role`, { role }, { preserveScroll: true });
        }
    }

    const [term, setTerm] = useState(search);

    function onSearch(event: FormEvent) {
        event.preventDefault();
        router.get("/users", { search: term }, { preserveState: true });
    }

    function confirmDeactivate(user: UserRow) {
        if (window.confirm("هل تريد إيقاف هذا الحساب؟ لن يتمكن المستخدم من تسجيل الدخول.")) {
            router.post(`/users/${user.id}/deactivate`);
        }
    }

    function confirmActivate(user: UserRow) {
        if (window.confirm("هل تريد إعادة تفعيل هذا الحساب؟")) {
            router.post(`/users/${user.id}/activate`);
        }
    }

    return (
        <AppLayout title="المستخدمون">
            <div className="app-page">
                <PageHeader title="المستخدمون" description="إنشاء حسابات البرنامج وإيقافها." />
                <p className="alert alert-warning" role="note">
                    {mfaNote}
                </p>
                <div className="d-flex flex-column flex-md-row gap-2 justify-content-between mb-3">
                    <form className="d-flex gap-2 w-100" onSubmit={onSearch} style={{ maxWidth: "36rem" }}>
                        <label className="visually-hidden" htmlFor="user-search">
                            بحث بالاسم أو البريد
                        </label>
                        <input
                            id="user-search"
                            className="form-control"
                            style={{ minHeight: "48px" }}
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            placeholder="الاسم أو البريد الإلكتروني"
                        />
                        <button type="submit" className="btn btn-outline-secondary" style={{ minHeight: "48px" }}>
                            بحث
                        </button>
                    </form>
                    <Link href="/users/create" className="btn btn-primary" style={{ minHeight: "48px" }}>
                        إضافة حساب
                    </Link>
                </div>
                {errors.role ? (
                    <p className="alert alert-danger" role="alert">
                        {errors.role}
                    </p>
                ) : null}
                <SectionCard title="الحسابات">
                    {users.data.length === 0 ? (
                        <EmptyState title="لا توجد حسابات مطابقة." />
                    ) : (
                        <ul className="list-group list-group-flush">
                            {users.data.map((user) => (
                                <li key={user.id} className="list-group-item px-0">
                                    <div className="d-flex flex-column flex-md-row justify-content-between gap-2">
                                        <div>
                                            <div className="fw-semibold">{user.name}</div>
                                            <div>{user.email}</div>
                                            {user.id !== currentUserId ? (
                                                <div className="mt-1" style={{ maxWidth: "16rem" }}>
                                                    <label className="visually-hidden" htmlFor={`role-${user.id}`}>
                                                        الدور
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
                                                <div>{user.role_label}</div>
                                            )}
                                            <div>{user.is_active ? "نشط" : "موقوف"}</div>
                                        </div>
                                        {user.id !== currentUserId ? (
                                            user.is_active ? (
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-danger"
                                                    style={{ minHeight: "44px" }}
                                                    onClick={() => confirmDeactivate(user)}
                                                >
                                                    إيقاف الحساب
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-success"
                                                    style={{ minHeight: "44px" }}
                                                    onClick={() => confirmActivate(user)}
                                                >
                                                    تفعيل الحساب
                                                </button>
                                            )
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </SectionCard>
            </div>
        </AppLayout>
    );
}

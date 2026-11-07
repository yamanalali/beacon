import { Link, useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import { useT } from "../../Hooks/useT";
import { GuestAuthLayout } from "./GuestAuthLayout";

export default function AcceptInvitation({
    invitation,
    token,
}: {
    invitation: { name: string; email: string };
    token: string;
}) {
    const t = useT();
    const form = useForm({ password: "", password_confirmation: "" });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/invitations/${token}`);
    }

    return (
        <GuestAuthLayout title={t("auth.invitation.title")}>
            <h2 className="h5 mb-2">{t("auth.invitation.welcome", { name: invitation.name })}</h2>
            <p className="mb-3">
                {t("auth.invitation.choose_password")}{" "}
                <bdi>{invitation.email}</bdi>
            </p>
            <form onSubmit={submit} noValidate>
                <label htmlFor="invite-password" className="form-label">
                    {t("auth.invitation.password_hint")}
                </label>
                <input
                    id="invite-password"
                    type="password"
                    autoComplete="new-password"
                    minLength={12}
                    required
                    className="form-control mb-3"
                    value={form.data.password}
                    onChange={(e) => form.setData("password", e.target.value)}
                />
                <label htmlFor="invite-confirmation" className="form-label">
                    {t("auth.reset.confirm")}
                </label>
                <input
                    id="invite-confirmation"
                    type="password"
                    autoComplete="new-password"
                    required
                    className="form-control mb-3"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData("password_confirmation", e.target.value)}
                />
                {Object.values(form.errors).map((error, i) => (
                    <p className="text-danger" role="alert" key={i}>
                        {error}
                    </p>
                ))}
                <button className="btn btn-primary w-100" style={{ minHeight: "48px" }} disabled={form.processing}>
                    {t("auth.invitation.activate")}
                </button>
            </form>
            <Link href="/login" className="d-block mt-3">
                {t("auth.login.title")}
            </Link>
        </GuestAuthLayout>
    );
}

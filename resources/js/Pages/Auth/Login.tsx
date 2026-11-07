import { Link, useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { useT } from "../../Hooks/useT";
import { GuestAuthLayout } from "./GuestAuthLayout";

type DemoAccounts = {
    password: string;
    accounts: { email: string; role: string }[];
} | null;

export default function Login({
    demoAccounts = null,
}: {
    demoAccounts?: DemoAccounts;
}) {
    const t = useT();
    const form = useForm({
        email: "",
        password: "",
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post("/login");
    }

    return (
        <GuestAuthLayout title={t("auth.login.title")}>
            <form onSubmit={submit} noValidate>
                <TextInput
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="username"
                    label={t("auth.login.email")}
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(value) => form.setData("email", value)}
                />
                <TextInput
                    id="password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    label={t("auth.login.password")}
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(value) => form.setData("password", value)}
                />
                <button
                    type="submit"
                    className="btn btn-primary w-100"
                    style={{ minHeight: "48px" }}
                    disabled={form.processing}
                >
                    {t("auth.login.submit")}
                </button>
                <p className="mt-3 mb-0">
                    <Link href="/forgot-password">{t("auth.login.forgot")}</Link>
                </p>
                <p className="mt-2 mb-0">
                    <Link href="/register">{t("register.link")}</Link>
                </p>
            </form>
            {demoAccounts ? (
                <aside
                    className="border rounded p-3 mt-4 small"
                    aria-label={t("auth.demo.title")}
                >
                    <p className="fw-semibold mb-2">{t("auth.demo.dev_only")}</p>
                    <ul className="list-unstyled mb-2">
                        {demoAccounts.accounts.map((account) => (
                            <li
                                key={account.email}
                                className="d-flex justify-content-between gap-2 py-1"
                            >
                                <span>{account.role}</span>
                                <button
                                    type="button"
                                    className="btn btn-link btn-sm p-0"
                                    dir="ltr"
                                    onClick={() =>
                                        form.setData({
                                            email: account.email,
                                            password: demoAccounts.password,
                                        })
                                    }
                                >
                                    {account.email}
                                </button>
                            </li>
                        ))}
                    </ul>
                    <p className="mb-0">
                        {t("auth.demo.password")}:{" "}
                        <code dir="ltr">{demoAccounts.password}</code>
                    </p>
                </aside>
            ) : null}
        </GuestAuthLayout>
    );
}

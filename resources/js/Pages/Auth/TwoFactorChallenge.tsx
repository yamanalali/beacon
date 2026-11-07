import { useForm } from "@inertiajs/react";
import { FormEvent, useState } from "react";
import { TextInput } from "../../Components/Forms/TextInput";
import { useT } from "../../Hooks/useT";
import { GuestAuthLayout } from "./GuestAuthLayout";

export default function TwoFactorChallenge() {
    const t = useT();
    const [useRecovery, setUseRecovery] = useState(false);
    const form = useForm({ code: "", recovery_code: "" });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) =>
            useRecovery
                ? { recovery_code: data.recovery_code }
                : { code: data.code },
        );
        form.post("/two-factor-challenge");
    }

    return (
        <GuestAuthLayout title={t("auth.two_factor.title")}>
            <form onSubmit={submit} noValidate>
                {useRecovery ? (
                    <TextInput
                        id="recovery_code"
                        name="recovery_code"
                        autoComplete="off"
                        label={t("auth.two_factor.recovery")}
                        value={form.data.recovery_code}
                        error={form.errors.recovery_code}
                        onChange={(value) =>
                            form.setData("recovery_code", value)
                        }
                    />
                ) : (
                    <TextInput
                        id="code"
                        name="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        label={t("auth.two_factor.app_code")}
                        value={form.data.code}
                        error={form.errors.code}
                        onChange={(value) => form.setData("code", value)}
                    />
                )}
                <button
                    type="submit"
                    className="btn btn-primary w-100"
                    style={{ minHeight: "48px" }}
                    disabled={form.processing}
                >
                    {t("auth.two_factor.submit")}
                </button>
                <p className="mt-3 mb-0">
                    <button
                        type="button"
                        className="btn btn-link p-0"
                        onClick={() => setUseRecovery(!useRecovery)}
                    >
                        {useRecovery
                            ? t("auth.two_factor.use_app")
                            : t("auth.two_factor.use_recovery")}
                    </button>
                </p>
            </form>
        </GuestAuthLayout>
    );
}

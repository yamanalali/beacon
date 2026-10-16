import axios, { type Method } from "axios";
import { useRef, useState } from "react";

const api = axios.create({
    headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
});

/** JSON endpoints share Laravel's same-origin XSRF cookie, validation and rate limits. */
export function useJsonAction() {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const pending = useRef(false);
    async function request<T>(
        method: Method,
        url: string,
        data?: unknown,
    ): Promise<T | undefined> {
        if (pending.current) return;
        pending.current = true;
        setBusy(true);
        setError(null);
        try {
            return (await api.request<T>({ method, url, data })).data;
        } catch (cause) {
            let message = "تعذر إتمام الطلب. تحقق من الاتصال وحاول مجدداً.";
            if (axios.isAxiosError(cause)) {
                const status = cause.response?.status;
                if (status === 422) {
                    const errors = cause.response?.data?.errors as
                        | Record<string, string[]>
                        | undefined;
                    message =
                        Object.values(errors ?? {})
                            .flat()
                            .join(" ") || "راجع الحقول المطلوبة.";
                } else if (status === 401 || status === 419)
                    message =
                        "انتهت الجلسة. أعد تحميل الصفحة وسجّل الدخول مجدداً.";
                else if (status === 403)
                    message = "ليس لديك صلاحية لتنفيذ هذا الإجراء.";
                else if (status === 429)
                    message =
                        "طلبات كثيرة خلال وقت قصير. انتظر قليلاً وحاول مجدداً.";
            }
            setError(message);
        } finally {
            pending.current = false;
            setBusy(false);
        }
    }
    return { request, busy, error };
}

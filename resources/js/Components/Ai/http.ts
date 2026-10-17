/** JSON POST to Laravel endpoints with the XSRF cookie (same contract as voiceApi). */
export type JsonResult<T> =
    | { ok: true; status: number; data: T }
    | { ok: false; status: number; message: string; errors?: Record<string, string[]> };

function xsrf(): string | null {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : null;
}

const FALLBACK: Record<number, string> = {
    403: "ليست لديك صلاحية لهذا الإجراء.",
    404: "العنصر غير موجود.",
    419: "انتهت الجلسة، حدّث الصفحة وحاول مجدداً.",
    429: "طلبات كثيرة، انتظر قليلاً ثم حاول مجدداً.",
};

export async function postJson<T>(
    url: string,
    body: Record<string, unknown> | null,
    fetchImpl: typeof fetch = fetch,
    method: "POST" | "PUT" = "POST",
): Promise<JsonResult<T>> {
    const headers: Record<string, string> = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    if (body) headers["Content-Type"] = "application/json";
    const token = xsrf();
    if (token) headers["X-XSRF-TOKEN"] = token;
    let res: Response;
    try {
        res = await fetchImpl(url, { method, headers, credentials: "same-origin", body: body ? JSON.stringify(body) : undefined });
    } catch {
        return { ok: false, status: 0, message: "تعذّر الاتصال بالخادم. تحقق من الشبكة وحاول مجدداً." };
    }
    let json: unknown = null;
    try {
        json = await res.json();
    } catch {
        json = null;
    }
    if (res.ok) return { ok: true, status: res.status, data: json as T };
    const j = (json ?? {}) as { message?: string; errors?: Record<string, string[]> };
    const first = j.errors ? Object.values(j.errors)[0]?.[0] : undefined;
    return {
        ok: false,
        status: res.status,
        message: first ?? FALLBACK[res.status] ?? "حدث خطأ غير متوقع. حاول مجدداً.",
        errors: j.errors,
    };
}

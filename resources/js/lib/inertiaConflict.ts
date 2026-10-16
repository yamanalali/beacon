/**
 * Inertia asset-version mismatch: the server answers 409 with X-Inertia-Location.
 * Do a full page load to that URL (fallback: reload) so the new assets are picked up.
 * Returns true when the response was handled.
 */
type HeaderBag = Record<string, string | undefined> | Headers | undefined | null;

export function headerValue(headers: HeaderBag, name: string): string | null {
    if (!headers) return null;
    if (typeof (headers as Headers).get === "function") return (headers as Headers).get(name);
    const wanted = name.toLowerCase();
    for (const [k, v] of Object.entries(headers as Record<string, string | undefined>)) {
        if (k.toLowerCase() === wanted && v) return v;
    }
    return null;
}

export function handleInertiaConflict(
    response: { status: number; headers?: HeaderBag } | null | undefined,
    loc: Pick<Location, "assign" | "reload"> = window.location,
): boolean {
    if (!response || response.status !== 409) return false;
    const target = headerValue(response.headers, "X-Inertia-Location");
    if (target) loc.assign(target);
    else loc.reload();
    return true;
}
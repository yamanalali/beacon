import { usePage } from "@inertiajs/react";

type Dict = Record<string, unknown>;

function lookup(dict: Dict, key: string): string | undefined {
    if (typeof dict[key] === "string") {
        return dict[key] as string;
    }

    const parts = key.split(".");
    let cur: unknown = dict;

    for (const part of parts) {
        if (cur === null || typeof cur !== "object" || !(part in (cur as Dict))) {
            return undefined;
        }
        cur = (cur as Dict)[part];
    }

    return typeof cur === "string" ? cur : undefined;
}

function interpolate(template: string, params?: Record<string, string | number>): string {
    if (!params) {
        return template;
    }

    return template.replace(/\{(\w+)\}/g, (_, name: string) =>
        params[name] !== undefined ? String(params[name]) : `{${name}}`,
    );
}

export function translate(
    translations: Dict | undefined,
    key: string,
    params?: Record<string, string | number>,
    fallback?: string,
): string {
    const value = translations ? lookup(translations, key) : undefined;
    return interpolate(value ?? fallback ?? key, params);
}

export function useT() {
    const { translations } = usePage().props as { translations?: Dict };

    return (key: string, params?: Record<string, string | number>, fallback?: string) =>
        translate(translations, key, params, fallback);
}

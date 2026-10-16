import type { PageProps } from "@inertiajs/core";

export type AuthUser = {
    name: string;
    role: string | null;
};

/** One entry of `auth.navigation`: built server-side from the same policies/middleware that protect the page. */
export type NavigationItem = {
    key: string;
    label: string;
    href: string;
    icon: string;
    group: "main" | "enterprise" | "mfi" | "ai" | "admin" | "operations" | "account" | (string & {});
    active: boolean;
};

export type BeaconSharedProps = PageProps & {
    locale: string;
    dir: "rtl" | "ltr";
    auth: {
        user: AuthUser | null;
        navigation: NavigationItem[];
    };
    flash?: {
        success?: string | null;
    };
};

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: BeaconSharedProps;
    }
}

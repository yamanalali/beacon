import type { PageProps } from "@inertiajs/core";

export type AuthUser = {
    name: string;
    role: string | null;
};

export type BeaconSharedProps = PageProps & {
    locale: string;
    dir: "rtl" | "ltr";
    auth: {
        user: AuthUser | null;
    };
};

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: BeaconSharedProps;
    }
}

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

export type QuickRecord = {
    enterprise_public_id: string;
    /** POST multipart `file` here; uploads, reads the receipt, redirects to extractions.show. */
    upload_url: string;
    /** POST multipart `audio` (JSON); null when voice is disabled. */
    voice_url: string | null;
    manual_url: string;
};

export type BeaconSharedProps = PageProps & {
    locale: string;
    dir: "rtl" | "ltr";
    auth: {
        user: AuthUser | null;
        navigation: NavigationItem[];
        can: {
            record_transaction: boolean;
        };
        quick_record: QuickRecord | null;
    };
    flash?: {
        success?: string | null;
        info?: string | null;
        created_document_id?: string | null;
    };
};

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: BeaconSharedProps;
    }
}

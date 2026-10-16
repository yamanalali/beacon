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

/** Sidebar section keys (same as NavigationItem.key), computed server-side from route middleware + policies. */
export type NavigationKey =
    | "dashboard.sme"
    | "dashboard.beacon"
    | "enterprises"
    | "transactions"
    | "documents"
    | "reports"
    | "readiness"
    | "compliance"
    | "referrals"
    | "mfi.referrals"
    | "ai.assistant"
    | "ai.escalations"
    | "ai.knowledge"
    | "notifications"
    | "admin"
    | "admin.users"
    | "admin.audit-logs"
    | "admin.compliance"
    | "admin.readiness"
    | "admin.mfis"
    | "admin.knowledge"
    | "operations.security"
    | "operations.backups"
    | "operations.data-control"
    | "account"
    | "account.sessions"
    | "account.two-factor";

/** `auth.can`: true only when the user can open that section; `record_transaction` gates «سجّل معاملة». */
export type AuthCan = {
    "dashboard.sme": boolean;
    "dashboard.beacon": boolean;
    "enterprises": boolean;
    "transactions": boolean;
    "documents": boolean;
    "reports": boolean;
    "readiness": boolean;
    "compliance": boolean;
    "referrals": boolean;
    "mfi.referrals": boolean;
    "ai.assistant": boolean;
    "ai.escalations": boolean;
    "ai.knowledge": boolean;
    "notifications": boolean;
    "admin": boolean;
    "admin.users": boolean;
    "admin.audit-logs": boolean;
    "admin.compliance": boolean;
    "admin.readiness": boolean;
    "admin.mfis": boolean;
    "admin.knowledge": boolean;
    "operations.security": boolean;
    "operations.backups": boolean;
    "operations.data-control": boolean;
    "account": boolean;
    "account.sessions": boolean;
    "account.two-factor": boolean;
    record_transaction: boolean;
};

export type BeaconSharedProps = PageProps & {
    locale: string;
    dir: "rtl" | "ltr";
    auth: {
        user: AuthUser | null;
        navigation: NavigationItem[];
        can: AuthCan;
        quick_record: QuickRecord | null;
    };
    flash?: {
        success?: string | null;
        warning?: string | null;
        info?: string | null;
        created_document_id?: string | null;
    };
};

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: BeaconSharedProps;
    }
}

/** Server-built dashboard props (app/Dashboard). React never computes KPIs. */
import type { Indicator } from "../Pages/Dashboard/Beacon";
import type { SummaryItem } from "../Pages/Dashboard/Sme";

export type MoneyString = string;

export type SmePeriodTotals = {
    from: string;
    to: string;
    income: MoneyString;
    expense: MoneyString;
    net: MoneyString;
    count: number;
};

export type SmeDashboardKpis = {
    /** TODO: BUSINESS DECISION REQUIRED — base currency; totals only include this currency. */
    currency: string;
    current_month: SmePeriodTotals;
    previous_month: SmePeriodTotals;
    other_currencies: string[];
    transaction_count: number;
    pending_extractions_count: number;
    readiness_score: string | null;
    overdue_compliance_count: number;
    upcoming_compliance_count: number;
    open_escalations_count: number;
};

export type SmeRecentTransaction = {
    public_id: string;
    type: "sale" | "income" | "purchase" | "expense";
    direction: "income" | "expense";
    transaction_date: string;
    amount: MoneyString;
    currency: string;
    category: string;
    counterparty: string | null;
    url: string;
};

export type SmeComplianceItem = {
    id: number;
    title: string;
    due_date: string;
    status: string;
    overdue: boolean;
};

export type DashboardAlert = {
    key: "overdue_compliance" | "pending_reviews" | "open_escalations";
    severity: "danger" | "warning" | "info";
    count: number;
    title: string;
    href: string | null;
};

export type SmeDashboardProps = {
    recentActivity: SummaryItem[];
    outstandingActions: SummaryItem[];
    upcomingDeadlines: SummaryItem[];
    readinessStatus: string | null;
    referralStatus: string | null;
    enterprise: { public_id: string; business_name: string } | null;
    kpis: SmeDashboardKpis | null;
    recent_transactions: SmeRecentTransaction[];
    compliance: { overdue: SmeComplianceItem[]; upcoming: SmeComplianceItem[] };
    alerts: DashboardAlert[];
};

export type ReferralStatus =
    | "draft"
    | "shared"
    | "under_review"
    | "info_requested"
    | "decided"
    | "outcome_recorded";

export type BeaconProgrammeData = {
    scope: "all" | "assigned";
    kpis: {
        enterprises_count: number;
        /** TODO: BUSINESS DECISION REQUIRED */
        active_enterprises_count: number | null;
        /** TODO: BUSINESS DECISION REQUIRED */
        enterprises_by_readiness_band: { band: string; count: number }[] | null;
        open_escalations_count: number;
        overdue_compliance_count: number;
        referrals_by_status: { status: ReferralStatus; label: string; count: number }[];
    };
    recent_referrals: {
        public_id: string;
        status: ReferralStatus;
        status_label: string;
        enterprise: { public_id: string | null; business_name: string | null };
        institution: string | null;
        updated_at: string | null;
    }[];
};

export type BeaconDashboardProps = {
    indicators: Indicator[];
    filters: Record<string, string>;
    filterOptions: { sectors: unknown[]; regions: unknown[]; genders: unknown[] };
    exportUrl: string | null;
    programme: BeaconProgrammeData;
};

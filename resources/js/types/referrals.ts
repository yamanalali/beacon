/** Shape of ReferralPackageBuilder output. Each section is present only when the SME consented to disclose it. */

export type ReferralEnterpriseProfile = {
    business_name: string;
    sector: string | null;
    /** Arabic label from the server; free text is returned as entered. */
    sector_label: string | null;
    location: string | null;
    founding_date: string | null;
    employee_count: number | null;
    registration_status: string | null;
    registration_status_label: string | null;
};

/** Decimal strings (e.g. "1000.30"); never converted between currencies. */
export type ReferralCurrencyTotals = {
    currency: string;
    income_total: string;
    expense_total: string;
    net: string;
    transaction_count: number;
};

export type ReferralFinancialSummary = {
    period: { from: string; to: string };
    currencies: ReferralCurrencyTotals[];
};

export type ReferralDocument = { name: string; type: string; date: string | null };

export type ReferralPackage = {
    enterprise_profile?: ReferralEnterpriseProfile;
    readiness_assessment?: {
        // Decimal columns may arrive as strings.
        score: number | string | null;
        mandatory_conditions_met: boolean | null;
        band: string | null;
        finalized_at: string | null;
        rule_set_version: number | string | null;
        disclaimer: string;
    };
    compliance_status?: { requirement: string | null; version: number | null; status: string }[];
    financial_summary?: ReferralFinancialSummary;
    documents?: ReferralDocument[];
};

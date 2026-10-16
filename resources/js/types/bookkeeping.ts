import type { SelectOption } from "../Components/Forms/SelectInput";

export type TransactionRow = {
    public_id: string;
    type: "sale" | "income" | "purchase" | "expense";
    type_label: string;
    is_inflow: boolean;
    transaction_date: string | null;
    amount: string;
    currency: string;
    category: string;
    category_label: string;
    payment_status: "paid" | "partially_paid" | "unpaid";
    payment_status_label: string;
    outstanding: string;
    counterparty: string | null;
    description: string | null;
    status: "posted" | "superseded";
    status_label: string;
    is_superseded: boolean;
};

export type BookkeepingOptions = {
    types: SelectOption[];
    payment_statuses: SelectOption[];
    currencies: SelectOption[];
    default_currency: string;
    categories: Record<string, SelectOption[]>;
};

export type EnterpriseRef = { public_id: string; business_name: string };

export type DocumentRow = {
    public_id: string;
    title: string;
    mime_type: string;
    is_image: boolean;
    is_previewable: boolean;
    size_kb: number;
    uploaded_at: string | null;
    uploader_name: string | null;
    transaction_public_id: string | null;
};

/** Expense/income suggestion on Extractions/Show (and voice drafts). Suggestion only; user confirms. */
export type SuggestedTransactionType = "expense" | "income" | null;

export type TransactionTypeOption = { value: "expense" | "income"; label: string };

export type TransactionTypeSuggestionProps = {
    suggested_transaction_type: SuggestedTransactionType;
    transaction_type_options: TransactionTypeOption[];
};

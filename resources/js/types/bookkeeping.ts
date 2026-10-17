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
    status: "posted" | "superseded" | "pending_approval";
    status_label: string;
    is_superseded: boolean;
};

/** Optional invoice line item; all numeric fields are numbers. */
export type TransactionItem = {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
};

/** Payload item for store / correct / receipt confirm (`items[]`). `total` optional: server computes quantity × unit_price. */
export type TransactionItemInput = {
    description: string;
    quantity: number | string;
    unit_price: number | string;
    total?: number | string | null;
};

/** Suggested item from a receipt/voice draft; unread values are null. */
export type SuggestedTransactionItem = {
    description: string | null;
    quantity: number | null;
    unit_price: number | null;
    total: number | null;
};

/** Extra fields on Transactions/Show `transaction` and Transactions/Form `original`. */
export type TransactionItemsProps = {
    items: TransactionItem[];
    items_total: number | null;
    items_mismatch: boolean;
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
    can_download?: boolean;
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

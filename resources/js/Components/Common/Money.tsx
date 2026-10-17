import { formatMoney } from "../../lib/money";

/** Amount + symbol in an LTR isolate so it reads correctly inside Arabic text and tables. */
export function Money({
    amount,
    currency,
    signed,
    className,
}: {
    amount: string | number | null | undefined;
    currency: string | null | undefined;
    /** Prefix "+" / "−" by direction (value itself stays positive). */
    signed?: "in" | "out";
    className?: string;
}) {
    const text = formatMoney(amount, currency);
    const sign = text === "—" || !signed ? "" : signed === "in" ? "+" : "−";
    return (
        <bdi dir="ltr" className={`text-nowrap${className ? ` ${className}` : ""}`} data-money="">
            {sign}
            {text}
        </bdi>
    );
}

import type { ReactNode } from "react";

export type PillTone = "neutral" | "primary" | "success" | "warning" | "danger" | "info" | "accent";

/** Rounded status pill with a leading dot; colour is never the only signal (label is always text). */
export function StatusPill({ tone = "neutral", children }: { tone?: PillTone; children: ReactNode }) {
    return (
        <span className={`app-pill app-pill-${tone}`}>
            <span className="app-pill-dot" aria-hidden="true" />
            {children}
        </span>
    );
}

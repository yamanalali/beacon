import { Head } from "@inertiajs/react";
import type { ReactNode } from "react";

export function GuestAuthLayout({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="min-vh-100 d-flex align-items-center justify-content-center px-3 py-4" dir="rtl" lang="ar">
            <Head title={title} />
            <main className="w-100" style={{ maxWidth: "28rem" }}>
                <p className="mb-3 fw-semibold" style={{ color: "var(--app-primary, #1f4b3a)" }}>
                    بيكون
                </p>
                <section
                    className="border p-3 p-md-4"
                    style={{
                        background: "var(--app-surface, #fff)",
                        borderColor: "var(--app-border, #e7e5e4)",
                        borderRadius: "var(--app-radius, 0.75rem)",
                    }}
                >
                    <h1 className="h4 mb-4">{title}</h1>
                    {children}
                </section>
            </main>
        </div>
    );
}

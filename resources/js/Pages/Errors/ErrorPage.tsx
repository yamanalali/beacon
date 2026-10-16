import { Head } from "@inertiajs/react";
import { Brand } from "../../Components/Common/Brand";

type ErrorCopy = { title: string; description: string; icon: string };

// Arabic copy for each status. No technical details are ever shown here.
const COPY: Record<number, ErrorCopy> = {
    403: {
        title: "ما عندك صلاحية لهالصفحة",
        description: "هالصفحة مخصصة لدور تاني. إذا بتعتقد إنو لازم تشوفها، تواصل مع إدارة بيكون.",
        icon: "bi-shield-lock",
    },
    404: {
        title: "الصفحة مو موجودة",
        description: "يمكن الرابط غلط أو الصفحة انشالت.",
        icon: "bi-signpost-split",
    },
    419: {
        title: "انتهت الجلسة",
        description: "مر وقت طويل بدون نشاط. حدّث الصفحة وجرّب مرة تانية.",
        icon: "bi-hourglass-split",
    },
    429: {
        title: "طلبات كتير",
        description: "بعتت طلبات كتير بوقت قصير. استنى شوي وجرّب مرة تانية.",
        icon: "bi-speedometer2",
    },
    500: {
        title: "صار خطأ بالسيرفر",
        description: "في مشكلة عنّا وعم نشتغل عليها. جرّب بعد شوي.",
        icon: "bi-exclamation-octagon",
    },
    503: {
        title: "النظام تحت الصيانة",
        description: "عم نعمل صيانة قصيرة. ارجع بعد شوي.",
        icon: "bi-tools",
    },
};

export default function ErrorPage({ status, homeUrl, isGuest }: { status: number; homeUrl: string; isGuest: boolean }) {
    const copy = COPY[status] ?? COPY[500];

    return (
        <div className="app-auth" dir="rtl" lang="ar">
            <Head title={`${copy.title} | بيكون`} />
            <main className="app-auth-main" style={{ gridColumn: "1 / -1" }}>
                <div className="app-auth-mobile-brand d-flex justify-content-center">
                    <Brand />
                </div>
                <section className="app-auth-card text-center" role="alert">
                    <i
                        className={`bi ${copy.icon}`}
                        aria-hidden="true"
                        style={{ fontSize: "2.75rem", color: "var(--app-primary, #176b60)" }}
                    />
                    <span className="app-eyebrow d-block mt-2" style={{ fontVariantNumeric: "tabular-nums" }}>
                        {status}
                    </span>
                    <h1>{copy.title}</h1>
                    <p className="app-auth-intro">{copy.description}</p>
                    <div className="d-flex flex-wrap justify-content-center gap-2 mt-3">
                        <a href={homeUrl} className="btn btn-primary">
                            <i className="bi bi-house-door ms-1" aria-hidden="true" />
                            {isGuest ? "تسجيل الدخول" : "رجوع للوحتي"}
                        </a>
                        {status === 419 || status === 429 || status === 503 ? (
                            <button type="button" className="btn btn-outline-secondary" onClick={() => window.location.reload()}>
                                حدّث الصفحة
                            </button>
                        ) : null}
                    </div>
                </section>
            </main>
        </div>
    );
}

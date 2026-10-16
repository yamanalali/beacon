import { Head } from "@inertiajs/react";
import type { ReactNode } from "react";
import { Brand } from "../../Components/Common/Brand";

export function GuestAuthLayout({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="app-auth" dir="rtl" lang="ar">
            <Head title={`${title} | بيكون`} />
            <aside className="app-auth-story" aria-label="عن منصة بيكون">
                <Brand />
                <div className="app-auth-story__copy">
                    <span className="app-auth-eyebrow">
                        مساحة واحدة. رؤية أوضح.
                    </span>
                    <h2>
                        كل خطوة في أعمالك،
                        <br />
                        أقرب إلى النجاح.
                    </h2>
                    <p>
                        أدر سجلاتك المالية، تابع تقدّم منشأتك، واستعد لفرص جديدة
                        من مكان واحد.
                    </p>
                    <div className="app-auth-features">
                        <span>
                            <i className="bi bi-wallet2" aria-hidden="true" />
                            سجلات منظّمة
                        </span>
                        <span>
                            <i
                                className="bi bi-graph-up-arrow"
                                aria-hidden="true"
                            />
                            قرارات أوضح
                        </span>
                        <span>
                            <i className="bi bi-buildings" aria-hidden="true" />
                            أعمال أقوى
                        </span>
                    </div>
                </div>
                <p className="app-auth-story__footer">
                    بيكون · شريكك في تطوير الأعمال
                </p>
            </aside>
            <main className="app-auth-main">
                <div className="app-auth-mobile-brand">
                    <Brand />
                </div>
                <section className="app-auth-card">
                    <span className="app-eyebrow">
                        <span aria-hidden="true" /> أهلاً بك في بيكون
                    </span>
                    <h1>{title}</h1>
                    <p className="app-auth-intro">
                        مساحة عملك، بخطوات بسيطة وواضحة.
                    </p>
                    {children}
                </section>
                <p className="app-auth-footer">
                    منصة بيكون لإدارة المنشآت الصغيرة والمتوسطة
                </p>
            </main>
        </div>
    );
}

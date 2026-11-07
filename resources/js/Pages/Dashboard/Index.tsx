import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";
import { useT } from "../../Hooks/useT";

export default function Index() {
    const t = useT();

    return (
        <AppLayout title={t("common.home", undefined, "الرئيسية")}>
            <div className="app-page">
                <PageHeader
                    title={t("dashboard.index.what_now", undefined, "ماذا أفعل الآن؟")}
                    description={t("dashboard.index.complete_profile_hint", undefined, "أكمل ملف المنشأة حتى يظهر الإجراء التالي.")}
                />
                <div className="row g-3 g-lg-4">
                    <div className="col-12 col-lg-6">
                        <SectionCard title={t("dashboard.index.next_step", undefined, "الخطوة التالية")}>
                            <p className="mb-0">
                                {t(
                                    "dashboard.index.next_step_body",
                                    undefined,
                                    "أكمل ملف المنشأة. بعد اكتماله تظهر هنا أول خطوة يمكن تنفيذها.",
                                )}
                            </p>
                        </SectionCard>
                    </div>
                    <div className="col-12 col-lg-6">
                        <EmptyState
                            title={t("dashboard.index.empty_tasks_title", undefined, "لا توجد مهام مطلوبة حالياً")}
                            description={t(
                                "dashboard.index.empty_tasks_desc",
                                undefined,
                                "سيظهر هنا أول إجراء عندما يكتمل ملف المنشأة.",
                            )}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

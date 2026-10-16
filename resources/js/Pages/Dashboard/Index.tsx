import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { EmptyState } from "../../Components/Feedback/EmptyState";

export default function Index() {
    return (
        <AppLayout title="الرئيسية">
            <div className="app-page">
                <PageHeader
                    title="ماذا أفعل الآن؟"
                    description="أكمل ملف المنشأة حتى يظهر الإجراء التالي."
                />
                <div className="row g-3 g-lg-4">
                    <div className="col-12 col-lg-6">
                        <SectionCard title="الخطوة التالية">
                            <p className="mb-0">
                                أكمل ملف المنشأة. بعد اكتماله تظهر هنا أول خطوة يمكن تنفيذها.
                            </p>
                        </SectionCard>
                    </div>
                    <div className="col-12 col-lg-6">
                        <EmptyState
                            title="لا توجد مهام مطلوبة حالياً"
                            description="سيظهر هنا أول إجراء عندما يكتمل ملف المنشأة."
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { StatusBadge } from "../../Components/Feedback/StatusBadge";
import { useT } from "../../Hooks/useT";

type Processor = { key: string; label_ar: string; approved: boolean; agreement_reference: string | null; allowed_data: string[] };

export default function DataControl({ processors }: { processors: Processor[] }) {
    const t = useT();

    return (
        <AppLayout title={t("operations.data_control.title", undefined, "التحكم بالبيانات")}>
            <div className="app-page">
                <PageHeader
                    title={t("operations.data_control.page_title", undefined, "التحكم بالبيانات والأطراف الثالثة")}
                    description={t(
                        "operations.data_control.page_description",
                        undefined,
                        "بيانات المنشآت والبرنامج تحت سيطرة بيكون، ولا تُرسل لأي مزوّد خارجي قبل اعتماد اتفاقية معالجة بيانات.",
                    )}
                />
                <div className="d-flex flex-column gap-2">
                    {processors.map((p) => (
                        <article key={p.key} className="border rounded p-3 bg-white">
                            <div className="d-flex justify-content-between flex-wrap gap-2">
                                <strong>{p.label_ar}</strong>
                                <StatusBadge
                                    label={
                                        p.approved
                                            ? t("operations.data_control.approved", undefined, "معتمد")
                                            : t("operations.data_control.not_approved", undefined, "غير معتمد - المشاركة ممنوعة")
                                    }
                                    tone={p.approved ? "success" : "neutral"}
                                />
                            </div>
                            {p.agreement_reference ? (
                                <small className="d-block">
                                    {t("operations.data_control.agreement_ref", undefined, "مرجع الاتفاقية")}: {p.agreement_reference}
                                </small>
                            ) : null}
                            <small className="d-block text-muted">
                                {t("operations.data_control.allowed_data", undefined, "البيانات المسموحة")}:{" "}
                                {p.allowed_data.length ? p.allowed_data.join("، ") : t("common.none", undefined, "لا شيء")}
                            </small>
                        </article>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

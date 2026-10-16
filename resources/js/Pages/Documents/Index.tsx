import { Link } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { DocumentList } from "../../Components/Documents/DocumentList";
import { DocumentUploader } from "../../Components/Documents/DocumentUploader";
import { Pagination, type PaginationLink } from "../../Components/Navigation/Pagination";
import type { DocumentRow, EnterpriseRef } from "../../types/bookkeeping";

export default function Index({
    enterprise,
    documents,
    can_upload,
}: {
    enterprise: EnterpriseRef;
    documents: { data: DocumentRow[]; links: PaginationLink[] };
    can_upload: boolean;
}) {
    return (
        <AppLayout title="المستندات">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="المستندات" description={`${enterprise.business_name} — الإيصالات والفواتير والوثائق الخاصة بالمنشأة.`} />
                <div>
                    <Link href={`/enterprises/${enterprise.public_id}`} className="btn btn-link px-0">
                        العودة إلى ملف المنشأة
                    </Link>
                </div>
                <div className="row g-3">
                    {can_upload ? (
                        <div className="col-12 col-lg-4">
                            <SectionCard title="رفع مستند">
                                <DocumentUploader enterpriseId={enterprise.public_id} />
                            </SectionCard>
                        </div>
                    ) : null}
                    <div className={can_upload ? "col-12 col-lg-8" : "col-12"}>
                        <SectionCard title="كل المستندات">
                            <DocumentList documents={documents.data} />
                            <Pagination links={documents.links} />
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

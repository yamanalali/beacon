import { useState } from "react";
import { Link } from "@inertiajs/react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { useJsonAction } from "../../hooks/useJsonAction";

type Answer = {
    supported: boolean;
    ai_generated: boolean;
    label: string | null;
    answer: string;
    sources: { id: string; title: string }[];
    can_escalate: boolean;
};
export default function Assistant({
    ai_available,
    can_escalate,
    question_max_length,
}: {
    ai_available: boolean;
    can_escalate: boolean;
    question_max_length: number;
}) {
    const [question, setQuestion] = useState("");
    const [answer, setAnswer] = useState<Answer | null>(null);
    const [escalation, setEscalation] = useState<string | null>(null);
    const [answeredQuestion, setAnsweredQuestion] = useState("");
    const { request, busy, error } = useJsonAction();
    const valid = question.trim().length >= 3;
    return (
        <AppLayout title="المساعد">
            <div className="app-page">
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <PageHeader
                        title="كيف نقدر نساعدك؟"
                        description="اسأل عن السجلات المالية والامتثال واستخدام المنصة."
                    />
                    <Link
                        href="/ai/pages/escalations"
                        className="btn btn-outline-primary"
                    >
                        متابعة استفساراتي
                    </Link>
                </div>
                <div className="row g-3">
                    <div className="col-12 col-xl-8">
                        <SectionCard title="اكتب سؤالك">
                            {!ai_available ? (
                                <div className="alert alert-info">
                                    المساعد الذكي غير متاح حالياً. يمكنك تصفح
                                    الإرشادات المعتمدة أو إرسال سؤالك لفريق
                                    بيكون.
                                </div>
                            ) : null}
                            <form
                                onSubmit={async (e) => {
                                    e.preventDefault();
                                    if (!valid) return;
                                    const result = await request<Answer>(
                                        "post",
                                        "/ai/assistant/ask",
                                        { question: question.trim() },
                                    );
                                    if (result) {
                                        setAnswer(result);
                                        setAnsweredQuestion(question.trim());
                                        setEscalation(null);
                                    }
                                }}
                            >
                                <label
                                    htmlFor="question"
                                    className="form-label"
                                >
                                    سؤالك
                                </label>
                                <textarea
                                    id="question"
                                    className="form-control mb-2"
                                    rows={5}
                                    maxLength={question_max_length}
                                    minLength={3}
                                    required
                                    value={question}
                                    onChange={(e) => {
                                        setQuestion(e.target.value);
                                        setAnswer(null);
                                        setEscalation(null);
                                    }}
                                    aria-describedby="question-help"
                                />
                                <p
                                    id="question-help"
                                    className="small text-body-secondary"
                                >
                                    اكتب سؤالاً واضحاً، وتجنب مشاركة كلمات
                                    المرور أو بيانات حساسة. {question.length} /{" "}
                                    {question_max_length}
                                </p>
                                <div className="d-flex flex-wrap gap-2">
                                    {ai_available ? (
                                        <button
                                            className="btn btn-primary"
                                            disabled={busy || !valid}
                                        >
                                            {busy
                                                ? "جارٍ الإرسال…"
                                                : "اسأل المساعد"}
                                        </button>
                                    ) : null}
                                    {can_escalate ? (
                                        <button
                                            type="button"
                                            className="btn btn-outline-primary"
                                            disabled={
                                                busy ||
                                                !valid ||
                                                escalation !== null
                                            }
                                            onClick={async () => {
                                                const result = await request<{
                                                    id: string;
                                                }>("post", "/ai/escalations", {
                                                    question: question.trim(),
                                                });
                                                if (result)
                                                    setEscalation(result.id);
                                            }}
                                        >
                                            إرسال لفريق بيكون
                                        </button>
                                    ) : null}
                                </div>
                            </form>
                            {error ? (
                                <div
                                    className="alert alert-danger mt-3 mb-0"
                                    role="alert"
                                >
                                    {error}
                                </div>
                            ) : null}
                            {escalation ? (
                                <div
                                    className="alert alert-success mt-3 mb-0"
                                    role="status"
                                >
                                    تم إرسال سؤالك.{" "}
                                    <Link
                                        href={`/ai/pages/escalations/${escalation}`}
                                    >
                                        متابعة الاستفسار
                                    </Link>
                                </div>
                            ) : null}
                        </SectionCard>
                        {answer ? (
                            <div className="mt-3" aria-live="polite">
                                <SectionCard title="الإجابة">
                                    <p className="small text-body-secondary">
                                        {answeredQuestion}
                                    </p>
                                    {answer.ai_generated && answer.label ? (
                                        <div className="alert alert-warning">
                                            {answer.label}
                                        </div>
                                    ) : null}
                                    <p
                                        className="mb-0"
                                        style={{
                                            whiteSpace: "pre-wrap",
                                            overflowWrap: "anywhere",
                                        }}
                                    >
                                        {answer.answer}
                                    </p>
                                    {answer.sources.length ? (
                                        <div className="mt-3">
                                            <h3 className="h6">
                                                المراجع المعتمدة
                                            </h3>
                                            <ul className="mb-0">
                                                {answer.sources.map(
                                                    (source) => (
                                                        <li key={source.id}>
                                                            <Link
                                                                href={`/ai/pages/knowledge?search=${encodeURIComponent(source.title)}`}
                                                            >
                                                                {source.title}
                                                            </Link>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    ) : null}
                                </SectionCard>
                            </div>
                        ) : null}
                    </div>
                    <div className="col-12 col-xl-4">
                        <SectionCard title="إرشادات موثوقة">
                            <p className="text-body-secondary">
                                تصفح المعرفة المعتمدة من فريق بيكون. يمكنك إرسال
                                الأسئلة التي تحتاج متابعة إلى الفريق.
                            </p>
                            <Link
                                href="/ai/pages/knowledge"
                                className="btn btn-light w-100"
                            >
                                تصفح دليل المعرفة{" "}
                                <i
                                    className="bi bi-arrow-left ms-2"
                                    aria-hidden="true"
                                />
                            </Link>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

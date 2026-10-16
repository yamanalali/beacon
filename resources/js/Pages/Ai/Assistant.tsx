import { Link } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import AppLayout from "../../Layouts/AppLayout";
import { PageHeader } from "../../Components/Common/PageHeader";
import { SectionCard } from "../../Components/Common/SectionCard";
import { postJson } from "../../Components/Ai/http";

export type AssistantAnswer = {
    supported: boolean;
    ai_generated: boolean;
    label: string | null;
    answer: string;
    sources: { id: string; title: string }[];
    can_escalate: boolean;
};
type EscalationCreated = { id: string; status: string; message: string };

type Props = { ai_available: boolean; can_escalate: boolean; question_max_length: number; fetchImpl?: typeof fetch };

export default function Assistant({ ai_available, can_escalate, question_max_length, fetchImpl }: Props) {
    const [question, setQuestion] = useState("");
    const [asked, setAsked] = useState("");
    const [busy, setBusy] = useState<"ask" | "escalate" | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [answer, setAnswer] = useState<AssistantAnswer | null>(null);
    const [escalated, setEscalated] = useState<EscalationCreated | null>(null);
    const tooShort = question.trim().length < 3;

    async function ask(e: FormEvent) {
        e.preventDefault();
        if (tooShort || busy) return;
        setBusy("ask");
        setError(null);
        setEscalated(null);
        const r = await postJson<AssistantAnswer>("/ai/assistant/ask", { question: question.trim() }, fetchImpl);
        setBusy(null);
        if (r.ok) {
            setAnswer(r.data);
            setAsked(question.trim());
        } else setError(r.message);
    }

    async function escalate() {
        const q = (asked || question).trim();
        if (q.length < 3 || busy) return;
        setBusy("escalate");
        setError(null);
        const r = await postJson<EscalationCreated>("/ai/escalations", { question: q }, fetchImpl);
        setBusy(null);
        if (r.ok) setEscalated(r.data);
        else setError(r.message);
    }

    const showEscalate = can_escalate && (answer ? answer.can_escalate : !ai_available);

    return (
        <AppLayout title="المساعد الذكي">
            <div className="app-page d-flex flex-column gap-3">
                <PageHeader title="المساعد الذكي" description="اسأل عن مسك الدفاتر واستخدام المنصة والامتثال. الإجابات من قاعدة معرفة بيكون المعتمدة فقط." />
                {!ai_available ? (
                    <div className="alert alert-warning mb-0" role="status">
                        <i className="bi bi-info-circle me-2" aria-hidden="true" />
                        المساعد غير متاح حالياً. يمكنك إرسال سؤالك مباشرة إلى فريق بيكون.
                    </div>
                ) : null}
                <SectionCard title="سؤالك">
                    <form onSubmit={ask} noValidate>
                        <label htmlFor="ai-question" className="form-label">
                            اكتب سؤالك
                        </label>
                        <textarea
                            id="ai-question"
                            className="form-control"
                            rows={4}
                            maxLength={question_max_length}
                            value={question}
                            onChange={(e) => setQuestion(e.target.value)}
                            aria-describedby="ai-question-help"
                        />
                        <div id="ai-question-help" className="form-text d-flex justify-content-between">
                            <span>لا تشارك كلمات مرور أو بيانات بطاقات.</span>
                            <bdi dir="ltr">
                                {question.length}/{question_max_length}
                            </bdi>
                        </div>
                        <div className="d-grid d-sm-flex gap-2 mt-3">
                            {ai_available ? (
                                <button type="submit" className="btn btn-primary" disabled={tooShort || busy !== null}>
                                    {busy === "ask" ? <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" /> : <i className="bi bi-stars me-2" aria-hidden="true" />}
                                    اسأل المساعد
                                </button>
                            ) : null}
                            {showEscalate && !escalated ? (
                                <button type="button" className={ai_available ? "btn btn-outline-secondary" : "btn btn-primary"} onClick={escalate} disabled={(asked || question).trim().length < 3 || busy !== null}>
                                    {busy === "escalate" ? <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" /> : <i className="bi bi-person-raised-hand me-2" aria-hidden="true" />}
                                    أرسل السؤال لفريق بيكون
                                </button>
                            ) : null}
                        </div>
                    </form>
                </SectionCard>
                {error ? (
                    <div className="alert alert-danger mb-0" role="alert">
                        {error}
                    </div>
                ) : null}
                {busy === "ask" ? (
                    <p className="text-body-secondary mb-0" role="status">
                        جارٍ البحث في قاعدة المعرفة…
                    </p>
                ) : null}
                {answer ? (
                    <SectionCard title="الإجابة">
                        <div aria-live="polite">
                            {answer.label ? (
                                <span className="app-status app-status--neutral mb-2">
                                    <i className="bi bi-stars" aria-hidden="true" />
                                    <span>{answer.label}</span>
                                </span>
                            ) : null}
                            <p className="mb-3" style={{ whiteSpace: "pre-line" }}>
                                {answer.answer}
                            </p>
                            {answer.sources.length > 0 ? (
                                <>
                                    <h3 className="h6">المصادر</h3>
                                    <ul className="mb-0">
                                        {answer.sources.map((s) => (
                                            <li key={s.id}>{s.title}</li>
                                        ))}
                                    </ul>
                                </>
                            ) : null}
                        </div>
                    </SectionCard>
                ) : null}
                {escalated ? (
                    <div className="alert alert-success mb-0 d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
                        <span>{escalated.message}</span>
                        <Link href={`/ai/pages/escalations/${escalated.id}`} className="btn btn-sm btn-outline-secondary">
                            عرض الاستفسار
                        </Link>
                    </div>
                ) : null}
                <div>
                    <Link href="/ai/pages/escalations" className="btn btn-link px-0">
                        استفساراتي السابقة
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}

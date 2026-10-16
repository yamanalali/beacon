import { usePage } from "@inertiajs/react";
import { useCallback, useEffect, useId, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { ReceiptCapture } from "../Documents/ReceiptCapture";
import { VoiceRecorder } from "./VoiceRecorder";
import type { QuickRecord as QuickRecordProps } from "../../types/shared";

type View = "menu" | "photo" | "voice";

/** Visible only when the server allows recording and sent the quick_record links. */
export function useQuickRecord(): QuickRecordProps | null {
    const { auth } = usePage().props;
    return auth?.can?.record_transaction && auth.quick_record ? auth.quick_record : null;
}

export function withExtract(url: string): string {
    return /[?&]extract=/.test(url) ? url : `${url}${url.includes("?") ? "&" : "?"}extract=1`;
}

export function QuickRecordSheet({ qr, onClose }: { qr: QuickRecordProps; onClose: () => void }) {
    const [view, setView] = useState<View>("menu");
    const ref = useRef<HTMLDivElement>(null);
    const closeRef = useRef<HTMLButtonElement>(null);
    const titleId = useId();

    useEffect(() => {
        closeRef.current?.focus();
        const prev = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        const onKey = (e: KeyboardEvent) => {
            if (e.key === "Escape") { e.preventDefault(); onClose(); return; }
            if (e.key !== "Tab" || !ref.current) return;
            const f = ref.current.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex="0"]');
            const first = f[0], last = f[f.length - 1];
            if (!first || !last) return;
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        };
        window.addEventListener("keydown", onKey);
        return () => { document.body.style.overflow = prev; window.removeEventListener("keydown", onKey); };
    }, [onClose]);

    const title = view === "photo" ? "صوّر فاتورة" : view === "voice" ? "سجّل صوت" : "سجّل معاملة";
    return createPortal(
        <>
            <div className="offcanvas-backdrop fade show app-sheet-backdrop" onClick={onClose} aria-hidden="true" />
            <div ref={ref} className="offcanvas offcanvas-bottom show app-sheet" role="dialog" aria-modal="true" aria-labelledby={titleId} tabIndex={-1}>
                <div className="app-sheet__header">
                    {view !== "menu" ? (
                        <button type="button" className="app-icon-button" aria-label="رجوع" onClick={() => setView("menu")}>
                            <i className="bi bi-arrow-right" aria-hidden="true" />
                        </button>
                    ) : null}
                    <h2 id={titleId} className="app-sheet__title">{title}</h2>
                    <button ref={closeRef} type="button" className="app-icon-button" aria-label="إغلاق" onClick={onClose}>
                        <i className="bi bi-x-lg" aria-hidden="true" />
                    </button>
                </div>
                {view === "menu" ? (
                    <ul className="app-sheet__list">
                        <li>
                            <button type="button" className="app-side-link" onClick={() => setView("photo")}>
                                <i className="bi bi-camera" aria-hidden="true" /><span>صوّر فاتورة</span>
                            </button>
                        </li>
                        {qr.voice_url ? (
                            <li>
                                <button type="button" className="app-side-link" onClick={() => setView("voice")}>
                                    <i className="bi bi-mic" aria-hidden="true" /><span>سجّل صوت</span>
                                </button>
                            </li>
                        ) : null}
                        <li>
                            <a href={qr.manual_url} className="app-side-link">
                                <i className="bi bi-pencil-square" aria-hidden="true" /><span>إدخال يدوي</span>
                            </a>
                        </li>
                    </ul>
                ) : (
                    <div className="px-3 pb-4" style={{ overflowY: "auto" }}>
                        {view === "photo" ? (
                            <ReceiptCapture enterpriseId={qr.enterprise_public_id} currentIds={[]} uploadUrl={withExtract(qr.upload_url)} />
                        ) : qr.voice_url ? (
                            <VoiceRecorder voiceUrl={qr.voice_url} manualUrl={qr.manual_url} onDone={onClose} />
                        ) : null}
                    </div>
                )}
            </div>
        </>,
        document.body,
    );
}

/** The «سجّل معاملة» trigger. `variant="hero"`: big dashboard button; `variant="tab"`: bottom-bar centre tab. */
export function QuickRecordButton({ variant = "hero" }: { variant?: "hero" | "tab" }) {
    const qr = useQuickRecord();
    const [open, setOpen] = useState(false);
    const btn = useRef<HTMLButtonElement>(null);
    const close = useCallback(() => { setOpen(false); btn.current?.focus(); }, []);
    if (!qr) return null;
    return (
        <>
            {variant === "tab" ? (
                <button ref={btn} type="button" className="app-tab" aria-haspopup="dialog" aria-expanded={open} onClick={() => setOpen(true)}>
                    <span className="btn btn-primary rounded-circle d-inline-flex align-items-center justify-content-center" style={{ width: 44, height: 44, marginTop: -14 }} aria-hidden="true">
                        <i className="bi bi-plus-lg" />
                    </span>
                    <span className="app-tab__label">سجّل معاملة</span>
                </button>
            ) : (
                <button ref={btn} type="button" className="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2" style={{ minHeight: 64, fontSize: 18 }}
                    aria-haspopup="dialog" aria-expanded={open} onClick={() => setOpen(true)}>
                    <i className="bi bi-plus-circle" aria-hidden="true" />
                    سجّل معاملة
                </button>
            )}
            {open ? <QuickRecordSheet qr={qr} onClose={close} /> : null}
        </>
    );
}

/** Error panel with a retry action for failed/slow requests. */
export function RetryPanel({
    message = "تعذّر تحميل البيانات. تحقق من الاتصال وحاول مرة أخرى.",
    onRetry,
    busy = false,
}: {
    message?: string;
    onRetry: () => void;
    busy?: boolean;
}) {
    return (
        <div className="d-flex flex-column flex-sm-row align-items-sm-center gap-2 p-3 border rounded-3 bg-white" role="alert">
            <i className="bi bi-exclamation-triangle text-warning fs-5" aria-hidden="true" />
            <span className="flex-grow-1">{message}</span>
            <button type="button" className="btn btn-outline-primary" style={{ minHeight: 44 }} onClick={onRetry} disabled={busy}>
                {busy ? <span className="spinner-border spinner-border-sm me-1" aria-hidden="true" /> : <i className="bi bi-arrow-clockwise me-1" aria-hidden="true" />}
                إعادة المحاولة
            </button>
        </div>
    );
}
import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from "react";
import { useT } from "../../Hooks/useT";

type Props = {
    onChange: (dataUrl: string | null) => void;
};

export function SignaturePad({ onChange }: Props) {
    const t = useT();
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const drawing = useRef(false);
    const hasInkRef = useRef(false);
    const [hasInk, setHasInk] = useState(false);

    function prepareCanvas() {
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.clientWidth || 320;
        const height = canvas.clientHeight || 200;
        const previous = hasInkRef.current ? canvas.toDataURL("image/png") : null;
        canvas.width = Math.max(1, Math.floor(width * ratio));
        canvas.height = Math.max(1, Math.floor(height * ratio));
        const ctx = canvas.getContext("2d");
        if (!ctx) {
            return;
        }
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.25;
        ctx.lineCap = "round";
        ctx.lineJoin = "round";
        ctx.strokeStyle = "#1d3336";
        ctx.clearRect(0, 0, width, height);

        if (previous) {
            const image = new Image();
            image.onload = () => {
                ctx.drawImage(image, 0, 0, width, height);
            };
            image.src = previous;
        }
    }

    function exportPng(): string | null {
        const canvas = canvasRef.current;
        if (!canvas || !hasInkRef.current) {
            return null;
        }
        // Flatten onto white so invoice/PDF signatures stay readable.
        const exportCanvas = document.createElement("canvas");
        exportCanvas.width = canvas.width;
        exportCanvas.height = canvas.height;
        const ctx = exportCanvas.getContext("2d");
        if (!ctx) {
            return canvas.toDataURL("image/png");
        }
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
        ctx.drawImage(canvas, 0, 0);
        return exportCanvas.toDataURL("image/png");
    }

    useEffect(() => {
        prepareCanvas();
        const onResize = () => prepareCanvas();
        window.addEventListener("resize", onResize);
        return () => window.removeEventListener("resize", onResize);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function point(event: ReactPointerEvent<HTMLCanvasElement>) {
        const canvas = canvasRef.current;
        if (!canvas) {
            return { x: 0, y: 0 };
        }
        const rect = canvas.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    function start(event: ReactPointerEvent<HTMLCanvasElement>) {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext("2d");
        if (!canvas || !ctx) {
            return;
        }
        drawing.current = true;
        canvas.setPointerCapture(event.pointerId);
        const p = point(event);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function move(event: ReactPointerEvent<HTMLCanvasElement>) {
        if (!drawing.current) {
            return;
        }
        const ctx = canvasRef.current?.getContext("2d");
        if (!ctx) {
            return;
        }
        const p = point(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        if (!hasInkRef.current) {
            hasInkRef.current = true;
            setHasInk(true);
        }
    }

    function endStroke(event: ReactPointerEvent<HTMLCanvasElement>) {
        if (!drawing.current) {
            return;
        }
        drawing.current = false;
        try {
            canvasRef.current?.releasePointerCapture(event.pointerId);
        } catch {
            // ignore
        }
        if (hasInkRef.current) {
            onChange(exportPng());
        }
    }

    function clear() {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext("2d");
        if (!canvas || !ctx) {
            return;
        }
        ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
        hasInkRef.current = false;
        setHasInk(false);
        onChange(null);
    }

    return (
        <div className={`signature-pad${hasInk ? " is-inked" : ""}`}>
            <div className="signature-pad__frame">
                {!hasInk ? (
                    <p className="signature-pad__hint" aria-hidden="true">
                        <i className="bi bi-pen" />
                        <span>{t("onboarding.draw_hint")}</span>
                    </p>
                ) : null}
                <canvas
                    ref={canvasRef}
                    className="signature-pad-canvas"
                    onPointerDown={start}
                    onPointerMove={move}
                    onPointerUp={endStroke}
                    onPointerCancel={endStroke}
                    aria-label={t("onboarding.draw_label")}
                />
            </div>
            <div className="signature-pad__toolbar">
                <span className="signature-pad__status">
                    {hasInk ? t("onboarding.draw_ready") : t("onboarding.draw_waiting")}
                </span>
                <button
                    type="button"
                    className="btn btn-outline-secondary btn-sm"
                    onClick={clear}
                    disabled={!hasInk}
                    style={{ minHeight: 40 }}
                >
                    <i className="bi bi-eraser me-1" aria-hidden="true" />
                    {t("onboarding.clear_signature")}
                </button>
            </div>
        </div>
    );
}

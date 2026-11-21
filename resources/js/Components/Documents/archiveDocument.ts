import { router } from "@inertiajs/react";

/** Archive (hide) a document after confirmation. Soft-archive only — file stays on the server. */
export function archiveDocument(
    publicId: string,
    labels: { confirm: string; reasonPrompt: string; reasonFallback: string },
): void {
    if (!window.confirm(labels.confirm)) {
        return;
    }

    const typed = window.prompt(labels.reasonPrompt, labels.reasonFallback);
    const reason = (typed ?? "").trim();
    if (reason.length < 3) {
        return;
    }

    router.post(`/documents/${publicId}/archive`, { reason }, { preserveScroll: true });
}

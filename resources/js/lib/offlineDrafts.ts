export type LocalDraft<T> = { id: string; updatedAt: string; data: T };
const key = (scope: string, enterprise: string) => `beacon:drafts:${scope}:${enterprise}`;
export function readDrafts<T>(scope: string, enterprise: string): LocalDraft<T>[] {
    try {
        const value: unknown = JSON.parse(localStorage.getItem(key(scope, enterprise)) ?? '[]');
        return Array.isArray(value) ? value.filter((d) => d && typeof d.id === 'string' && typeof d.updatedAt === 'string' && d.data && typeof d.data === 'object').slice(0, 20) : [];
    } catch { return []; }
}
export function saveDraft<T>(scope: string, enterprise: string, draft: LocalDraft<T>) {
    const drafts = readDrafts<T>(scope, enterprise).filter((d) => d.id !== draft.id);
    if (drafts.length >= 20) throw new Error('احذف مسودة قديمة أولاً (الحد 20).');
    localStorage.setItem(key(scope, enterprise), JSON.stringify([draft, ...drafts]));
}
export function removeDraft(scope: string, enterprise: string, id: string) {
    localStorage.setItem(key(scope, enterprise), JSON.stringify(readDrafts(scope, enterprise).filter((d) => d.id !== id)));
}

export function clearDeviceDrafts() {
    try { for (const storageKey of Object.keys(localStorage)) if (storageKey.startsWith("beacon:drafts:")) localStorage.removeItem(storageKey); } catch { /* Storage can be disabled by the browser. */ }
}

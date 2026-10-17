import { afterEach, describe, expect, it } from 'vitest';
import { clearDeviceDrafts, readDrafts, removeDraft, saveDraft } from './offlineDrafts';
afterEach(() => localStorage.clear());
const draft = { id: 'id-1', updatedAt: '2026-10-17', data: { amount: '150' } };
describe('device drafts', () => {
    it('isolates accounts and enterprises', () => {
        saveDraft('a', 'one', draft);
        expect(readDrafts('a', 'one')).toEqual([draft]);
        expect(readDrafts('b', 'one')).toEqual([]);
        expect(readDrafts('a', 'two')).toEqual([]);
    });
    it('updates the same draft without changing the submission id and deletes it', () => {
        saveDraft('a', 'one', draft);
        saveDraft('a', 'one', { ...draft, data: { amount: '200' } });
        expect(readDrafts('a', 'one')).toHaveLength(1);
        removeDraft('a', 'one', draft.id);
        expect(readDrafts('a', 'one')).toEqual([]);
    });
    it('recovers corrupt local storage and clears only device drafts at logout', () => {
        localStorage.setItem('beacon:drafts:a:one', '{broken');
        expect(readDrafts('a', 'one')).toEqual([]);
        saveDraft('a', 'one', draft);
        localStorage.setItem('preferences', 'keep');
        clearDeviceDrafts();
        expect(readDrafts('a', 'one')).toEqual([]);
        expect(localStorage.getItem('preferences')).toBe('keep');
    });
    it('caps draft count instead of silently deleting old entries', () => {
        for (let i = 0; i < 20; i++) saveDraft('a', 'one', { ...draft, id: `id-${i}` });
        expect(() => saveDraft('a', 'one', { ...draft, id: 'overflow' })).toThrow();
        expect(readDrafts('a', 'one')).toHaveLength(20);
    });
});

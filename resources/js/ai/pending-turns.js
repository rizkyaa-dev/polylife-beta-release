/** Shared ownership of composer/edit turns, including disconnected observers. */
export class PendingAiTurns {
    constructor() {
        this.turns = new Map();
    }

    get(owner) { return this.turns.get(owner); }

    set(owner, turn) {
        if (this.isBlocked(owner)) throw new Error('Percakapan sebelumnya belum selesai dipulihkan.');
        this.turns.set(owner, turn);
    }

    delete(owner) { this.turns.delete(owner); }

    isBlocked(owner) {
        return [...this.turns.keys()].some(key => key !== owner);
    }

    get activeRunId() {
        for (const turn of this.turns.values()) {
            if (turn.accepted) return turn.accepted.run_id;
        }
        return null;
    }

    cancel(runId) {
        const cancelled = [];
        for (const [owner, turn] of this.turns) {
            if (turn.accepted?.run_id !== runId) continue;
            this.turns.delete(owner);
            cancelled.push({ owner, turn });
        }
        return cancelled;
    }
}

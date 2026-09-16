/** Connection failures are not evidence that the server-side operation failed. */
export function isDefinitiveFailure(error, accepted = false) {
    if (error.terminalRun) return true;
    if (accepted) return false;
    return Number.isInteger(error.httpStatus)
        && error.httpStatus >= 400 && error.httpStatus < 500
        && ![408, 429].includes(error.httpStatus);
}

export function terminalRunError(message, cancelled = false) {
    const error = new Error(message);
    error.terminalRun = true;
    if (cancelled) error.name = 'AbortError';
    return error;
}

/** A turn keeps its original identity until acceptance and completion are reconciled. */
export class PendingAiTurn {
    constructor(id, prompt, sessionId, accepted = null) {
        this.id = id;
        this.prompt = prompt;
        this.sessionId = sessionId;
        this.accepted = accepted;
        this.uncertain = false;
        this.message = null;
    }

    payload() {
        return { message: this.prompt, session_id: this.sessionId, request_id: this.id };
    }

    async reconcile(send) {
        if (!this.accepted) {
            try {
                const accepted = await send(this.payload());
                if (!Number.isInteger(accepted?.run_id) || !Number.isInteger(accepted?.session_id)) {
                    throw new Error('Server belum memberikan identitas proses yang valid. Coba periksa pengiriman lagi.');
                }
                this.accepted = accepted;
                this.uncertain = false;
            } catch (error) {
                if (!isDefinitiveFailure(error)) this.uncertain = true;
                throw error;
            }
        }
        return this.accepted;
    }
}

/** No generated HTML is inserted. Approval is per program and expires with its lease. */
export function requestClientApproval(root, claim, signal) {
    return new Promise(resolve => {
        if (signal.aborted) { resolve(false); return; }
        const card = document.createElement('section');
        card.className = 'ai-client-computation';
        const title = document.createElement('strong');
        title.textContent = 'Komputasi lokal eksperimental';
        const note = document.createElement('p');
        note.textContent = 'Skrip buatan AI berjalan dalam mesin terisolasi. Hasil dan akurasinya belum diverifikasi independen. Tidak memiliki akses jaringan atau data aplikasi.';
        const model = document.createElement('p');
        model.textContent = claim.model;
        const details = document.createElement('details');
        const summary = document.createElement('summary');
        summary.textContent = 'Lihat skrip, input dan rencana pemeriksaan';
        const code = document.createElement('pre');
        code.textContent = `${claim.program.source}\n\nInput:\n${JSON.stringify(claim.program.inputs, null, 2)}\n\nPemeriksaan:\n${claim.program.checks.join('\n')}`;
        details.append(summary, code);
        const allow = document.createElement('button');
        allow.type = 'button'; allow.textContent = 'Jalankan lokal';
        const decline = document.createElement('button');
        decline.type = 'button'; decline.textContent = 'Lewati';
        card.append(title, note, model, details, allow, decline);
        const finish = value => {
            clearTimeout(timer); signal.removeEventListener('abort', abort); card.remove(); resolve(value);
        };
        const abort = () => finish(false);
        const remaining = Math.max(0, Date.parse(claim.lease_expires_at) - Date.now() - CLIENT_EXECUTION_RESERVE_MS);
        const timer = setTimeout(() => finish(false), Math.min(60000, remaining));
        signal.addEventListener('abort', abort, { once: true });
        allow.addEventListener('click', () => finish(true), { once: true });
        decline.addEventListener('click', () => finish(false), { once: true });
        root.querySelector('[data-ai-message-list]').append(card);
    });
}

const CLIENT_EXECUTION_RESERVE_MS = 18000;

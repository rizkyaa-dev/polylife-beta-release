import { postJson } from './transport';

function settle(card, status, label, receipt = null) {
    card.dataset.status = status;
    card.querySelector('[data-proposal-status]').textContent = label;
    card.querySelector('[data-proposal-actions]').hidden = true;
    const note = card.querySelector('[data-proposal-note]');
    const destination = card.querySelector('[data-proposal-destination]');
    if (receipt?.message) {
        card.querySelector('[data-proposal-note-text]').textContent = receipt.message;
        note.hidden = false;
    } else {
        note.hidden = true;
    }
    if (receipt?.destination_url && receipt?.destination_label) {
        destination.href = receipt.destination_url;
        destination.textContent = receipt.destination_label;
        destination.hidden = false;
    } else {
        destination.hidden = true;
        destination.removeAttribute('href');
    }
}

function expire(card) {
    if (card.dataset.status !== 'pending' || card.getAttribute('aria-busy') === 'true') return false;
    if (Date.parse(card.dataset.expiresAt) <= Date.now()) {
        settle(card, 'expired', 'Kedaluwarsa. Minta proposal baru untuk melanjutkan.');
        return true;
    }
    return false;
}

export function initProposals(root) {
    const refreshExpiry = () => root.querySelectorAll('[data-ai-proposal]').forEach(expire);
    const timer = window.setInterval(refreshExpiry, 30000);
    window.addEventListener('pagehide', () => window.clearInterval(timer), { once: true });
    refreshExpiry();
    root.addEventListener('click', async event => {
        const button = event.target.closest('[data-proposal-confirm], [data-proposal-reject]');
        if (!button) return;
        const card = button.closest('[data-ai-proposal]');
        if (card.dataset.status !== 'pending' || expire(card) || card.getAttribute('aria-busy') === 'true') return;
        const confirm = button.hasAttribute('data-proposal-confirm');
        const error = card.querySelector('[data-proposal-error]');
        const buttons = card.querySelectorAll('button');
        const status = card.querySelector('[data-proposal-status]');
        error.hidden = true;
        card.setAttribute('aria-busy', 'true');
        buttons.forEach(item => { item.disabled = true; });
        status.textContent = confirm ? 'Menyimpan…' : 'Membatalkan…';
        try {
            const response = await postJson(confirm ? root.dataset.confirmUrl : root.dataset.rejectUrl, {
                action_id: card.dataset.actionId,
                ...(confirm ? { signature: card.dataset.signature } : {}),
            });
            settle(
                card,
                confirm ? 'confirmed' : 'rejected',
                confirm ? 'Sudah disimpan' : 'Dibatalkan',
                confirm ? response.receipt : { message: response.message }
            );
            status.tabIndex = -1;
            status.focus({ preventScroll: true });
        } catch (exception) {
            error.textContent = exception instanceof TypeError ? 'Koneksi terputus. Periksa jaringan lalu coba kembali.' : exception.message;
            error.hidden = false;
            status.textContent = 'Menunggu konfirmasi';
        } finally {
            card.setAttribute('aria-busy', 'false');
            buttons.forEach(item => { item.disabled = false; });
        }
    });
}

export function createProposal(root, proposal) {
    const card = root.querySelector('[data-ai-proposal-template]').content.firstElementChild.cloneNode(true);
    card.dataset.actionId = proposal.action_id;
    card.dataset.signature = proposal.signature;
    card.dataset.expiresAt = proposal.expires_at;
    card.querySelector('[data-proposal-summary]').textContent = proposal.summary;
    expire(card);
    return card;
}

import { getJson, postJson, sendJson } from './transport';
import { initHistory } from './history';
import { initProposals, createProposal } from './proposals';

const root = document.querySelector('[data-ai-workspace]');
if (root) initWorkspace(root);

function initWorkspace(root) {
    const input = root.querySelector('[data-ai-input]');
    const form = root.querySelector('[data-ai-form]');
    const send = root.querySelector('[data-ai-send]');
    const messages = root.querySelector('[data-ai-messages]');
    const list = root.querySelector('[data-ai-message-list]');
    const error = root.querySelector('[data-ai-error]');
    const errorText = error.querySelector('[data-ai-error-text]');
    const sendIcon = send.querySelector('[data-ai-send-icon]');
    const stopIcon = send.querySelector('[data-ai-stop-icon]');
    const history = initHistory(root.dataset.workspaceUrl);
    initThinkingControl(root);
    let sessionId = Number(root.dataset.sessionId) || null;
    let busy = false;
    let activeRunId = null;
    let stopping = false;
    const showError = message => {
        errorText.textContent = message;
        error.hidden = false;
    };
    const hideError = () => { error.hidden = true; errorText.textContent = ''; };
    const showThinking = () => {
        const existing = list.querySelector('[data-ai-thinking-indicator]');
        if (existing) return existing;
        const indicator = root.querySelector('[data-ai-thinking-template]').content.firstElementChild.cloneNode(true);
        list.append(indicator);
        return indicator;
    };
    const removeThinking = () => list.querySelector('[data-ai-thinking-indicator]')?.remove();
    root.querySelector('[data-ai-retry]')?.addEventListener('click', () => {
        if (!busy && input.value.trim()) form.requestSubmit();
    });

    const resize = () => {
        input.style.height = 'auto';
        input.style.height = `${input.scrollHeight}px`;
        const canStop = busy && activeRunId !== null && !stopping;
        send.disabled = busy ? !canStop : !input.value.trim();
        sendIcon.hidden = busy;
        stopIcon.hidden = !busy;
        send.setAttribute('aria-label', busy ? 'Hentikan respons' : 'Kirim pesan');
        send.title = busy ? 'Hentikan respons' : 'Kirim pesan';
    };
    send.addEventListener('click', async event => {
        if (!busy) return;
        event.preventDefault();
        if (!activeRunId || stopping) return;

        stopping = true;
        resize();
        try {
            const url = root.dataset.cancelRunUrlTemplate.replace('__RUN__', activeRunId);
            await postJson(url, {});
        } catch (exception) {
            showError(exception.message);
        } finally {
            stopping = false;
            resize();
        }
    });
    input.addEventListener('input', resize);
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && window.matchMedia('(pointer: fine)').matches) {
            event.preventDefault();
            if (!busy && input.value.trim()) form.requestSubmit();
        }
    });
    root.querySelectorAll('[data-ai-prompt]').forEach(button => {
        button.addEventListener('click', () => {
            input.value = button.dataset.aiPrompt;
            resize();
            input.focus();
        });
    });
    const scrollToLatest = () => { messages.scrollTop = messages.scrollHeight; };
    const appendMessage = (role, content, proposals = [], safeHtml = null, metadata = {}) => {
        const message = root.querySelector(`[data-ai-${role}-template]`).content.firstElementChild.cloneNode(true);
        const text = message.querySelector('[data-message-text]');
        if (role === 'assistant' && safeHtml !== null) {
            text.innerHTML = safeHtml;
        } else {
            text.textContent = content;
        }
        if (metadata.id) setMessageIdentity(message, metadata.id);
        if (metadata.branchId) message.dataset.branchId = metadata.branchId;
        if (role === 'assistant' && metadata.run) appendProcess(message, metadata.run);
        proposals.forEach(proposal => message.querySelector('[data-message-proposals]').append(createProposal(root, proposal)));
        list.append(message);
        scrollToLatest();
        return message;
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const prompt = input.value.trim();
        if (busy || !prompt) return;
        busy = true;
        hideError();
        root.classList.add('has-messages');
        root.querySelector('[data-ai-welcome]').hidden = true;
        root.querySelector('[data-ai-suggestions]').hidden = true;
        messages.hidden = false;
        const userMessage = appendMessage('user', prompt);
        showThinking();
        input.value = '';
        input.readOnly = true;
        form.setAttribute('aria-busy', 'true');
        resize();
        try {
            const data = await postJson(root.dataset.chatUrl, { message: prompt, session_id: sessionId });
            sessionId = data.session_id;
            root.dataset.sessionId = sessionId;
            setMessageIdentity(userMessage, data.user_message.id);
            userMessage.dataset.branchId = data.user_message.branch_id;
            activeRunId = data.run_id;
            resize();
            const completed = await waitForRun(root, data.run_id);
            removeThinking();
            appendMessage('assistant', completed.reply, completed.proposals ?? [], completed.reply_html ?? null, {
                id: completed.assistant_message?.id,
                branchId: completed.active_branch_id,
                run: completed.run,
            });
            history.update(sessionId, prompt.slice(0, 60));
            const url = new URL(root.dataset.workspaceUrl);
            url.searchParams.set('session', sessionId);
            window.history.replaceState(null, '', url);
        } catch (exception) {
            removeThinking();
            userMessage.remove();
            input.value = prompt;
            if (exception.name !== 'AbortError') {
                showError((exception instanceof TypeError ? 'Koneksi terputus. Periksa jaringanmu.' : exception.message) + ' Draft tetap tersedia.');
            }
        } finally {
            activeRunId = null;
            busy = false;
            input.readOnly = false;
            form.setAttribute('aria-busy', 'false');
            resize();
            if (window.matchMedia('(pointer: fine)').matches) input.focus({ preventScroll: true });
            scrollToLatest();
        }
    });

    initMessageInteractions(root, {
        isBusy: () => busy,
        setBusy: value => { busy = value; resize(); },
        setActiveRunId: value => { activeRunId = value; resize(); },
        getSessionId: () => sessionId,
    });
    initEarlierMessages(root, list);

    const dialog = root.querySelector('[data-ai-settings]');
    let opener;
    document.querySelectorAll('[data-ai-settings-open]').forEach(button => {
        button.addEventListener('click', () => {
            opener = button;
            document.dispatchEvent(new Event('sidebar:close'));
            dialog.showModal();
        });
    });
    dialog.querySelectorAll('[data-ai-settings-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
        const sidebarOpen = document.body.classList.contains('sidebar-open');
        const hiddenInSidebar = opener?.closest('#app-sidebar') && !window.matchMedia('(min-width: 1025px)').matches && !sidebarOpen;
        const target = opener && !hiddenInSidebar ? opener : root.querySelector('[data-ai-settings-open]');
        target?.focus({ preventScroll: true });
    });
    if (dialog.hasAttribute('data-has-errors')) dialog.showModal();
    initProposals(root);
    resize();
    scrollToLatest();

    const resumedRunId = Number(root.dataset.activeRunId) || null;
    if (resumedRunId) {
        void resumeActiveRun(resumedRunId);
    }

    async function resumeActiveRun(runId) {
        const pendingMessage = root.dataset.activeRunMessageId
            ? list.querySelector(`[data-message-id="${root.dataset.activeRunMessageId}"]`)
            : null;
        const pendingPrompt = root.dataset.activeRunPrompt ?? '';
        busy = true;
        activeRunId = runId;
        input.readOnly = true;
        form.setAttribute('aria-busy', 'true');
        showThinking();
        resize();
        scrollToLatest();

        try {
            const completed = await waitForRun(root, runId);
            removeThinking();
            appendMessage('assistant', completed.reply, completed.proposals ?? [], completed.reply_html ?? null, {
                id: completed.assistant_message?.id,
                branchId: completed.active_branch_id,
                run: completed.run,
            });
        } catch (exception) {
            removeThinking();
            pendingMessage?.remove();
            input.value = pendingPrompt;
            if (exception.name !== 'AbortError') {
                showError(`${exception.message} Draft tetap tersedia.`);
            }
        } finally {
            activeRunId = null;
            busy = false;
            input.readOnly = false;
            form.setAttribute('aria-busy', 'false');
            resize();
            scrollToLatest();
        }
    }
}

function setMessageIdentity(message, id) {
    message.dataset.messageId = id;
    const input = message.querySelector('[data-ai-edit-input]');
    const label = message.querySelector('label[for]');
    if (input && label) {
        input.id = `ai-edit-${id}`;
        label.htmlFor = input.id;
    }
}

function appendProcess(message, run) {
    if (!run?.steps?.length) return;

    const details = document.createElement('details');
    details.className = 'ai-process';
    details.dataset.aiProcess = '';
    const seconds = Math.max(1, Math.ceil((run.duration_ms ?? 0) / 1000));
    details.innerHTML = `<summary><span></span><svg class="ai-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary><ol class="ai-process-steps"></ol>`;
    details.querySelector('summary span').textContent = `Proses AI · ${seconds} dtk`;
    const steps = details.querySelector('ol');
    run.steps.forEach(step => {
        const item = document.createElement('li');
        item.className = `ai-process-step ai-process-step-${step.kind}`;
        item.dataset.status = step.status;
        const mark = document.createElement('span');
        mark.className = 'ai-process-step-mark';
        if (step.kind === 'tool_call') mark.textContent = '⌁';
        const copy = document.createElement('span');
        const label = document.createElement('strong');
        label.textContent = step.label;
        const meta = document.createElement('small');
        const type = step.kind === 'tool_call' ? 'Aktivitas alat' : 'Reasoning ringkas';
        const duration = step.duration_ms === null ? '' : ` · ${Math.max(1, Math.round(step.duration_ms / 1000))} dtk`;
        meta.textContent = `${type}${duration} · ${step.status === 'failed' ? 'Gagal' : 'Selesai'}`;
        copy.append(label, meta);
        item.append(mark, copy);
        steps.append(item);
    });
    message.querySelector('.ai-message-body').prepend(details);
}

function initMessageInteractions(root, state) {
    root.addEventListener('click', async event => {
        const message = event.target.closest('.ai-message-user');
        if (!message) return;

        if (event.target.closest('[data-ai-edit-open]')) {
            if (state.isBusy() || !message.dataset.messageId) return;
            setEditMode(message, true);
            return;
        }
        if (event.target.closest('[data-ai-edit-cancel]')) {
            setEditMode(message, false);
            return;
        }

        const versionButton = event.target.closest('[data-ai-version-branch]');
        if (versionButton && versionButton.dataset.aiVersionBranch && !state.isBusy()) {
            await activateBranch(root, versionButton.dataset.aiVersionBranch, versionButton);
        }
    });

    root.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const form = event.target.closest('[data-ai-edit-form]');
        if (form) {
            event.preventDefault();
            setEditMode(form.closest('.ai-message-user'), false);
        }
    });

    root.addEventListener('submit', async event => {
        const form = event.target.closest('[data-ai-edit-form]');
        if (!form) return;
        event.preventDefault();
        const message = form.closest('.ai-message-user');
        const input = form.querySelector('[data-ai-edit-input]');
        const error = form.querySelector('[data-ai-edit-error]');
        const prompt = input.value.trim();
        if (!prompt || state.isBusy() || !message.dataset.messageId) return;

        state.setBusy(true);
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button, textarea').forEach(control => { control.disabled = true; });
        error.hidden = true;
        try {
            const url = root.dataset.editUrlTemplate.replace('__MESSAGE__', message.dataset.messageId);
            const accepted = await sendJson(url, 'PATCH', { message: prompt });
            state.setActiveRunId(accepted.run_id);
            const data = await waitForRun(root, accepted.run_id);
            const workspaceUrl = new URL(root.dataset.workspaceUrl);
            workspaceUrl.searchParams.set('session', data.session_id);
            window.location.assign(workspaceUrl);
        } catch (exception) {
            if (exception.name !== 'AbortError') {
                error.textContent = `${exception.message} Draft edit tetap tersedia.`;
                error.hidden = false;
            }
            state.setActiveRunId(null);
            state.setBusy(false);
            form.setAttribute('aria-busy', 'false');
            form.querySelectorAll('button, textarea').forEach(control => { control.disabled = false; });
            input.focus();
        }
    });
}

function setEditMode(message, editing) {
    const text = message.querySelector('[data-message-text]');
    const form = message.querySelector('[data-ai-edit-form]');
    const actions = message.querySelector('[data-ai-message-actions]');
    const input = form.querySelector('[data-ai-edit-input]');
    if (editing) input.value = text.textContent;
    text.hidden = editing;
    form.hidden = !editing;
    actions.hidden = editing;
    if (editing) {
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    } else {
        message.querySelector('[data-ai-edit-open]')?.focus();
    }
}

async function activateBranch(root, branchId, button) {
    button.disabled = true;
    try {
        const url = root.dataset.activateBranchUrlTemplate.replace('__BRANCH__', branchId);
        const data = await postJson(url, {});
        window.location.assign(data.url);
    } catch (exception) {
        const error = root.querySelector('[data-ai-error]');
        const text = error.querySelector('[data-ai-error-text]') ?? error;
        text.textContent = exception.message;
        error.hidden = false;
        button.disabled = false;
    }
}

function initEarlierMessages(root, list) {
    list.addEventListener('click', async event => {
        const button = event.target.closest('[data-ai-load-earlier]');
        if (!button) return;
        const firstMessage = list.querySelector('.ai-message[data-message-id]');
        if (!firstMessage || !root.dataset.sessionId) return;
        button.disabled = true;
        button.textContent = 'Memuat percakapan sebelumnya…';
        try {
            const url = new URL(root.dataset.messagesUrlTemplate.replace('__SESSION__', root.dataset.sessionId), window.location.origin);
            url.searchParams.set('before', firstMessage.dataset.messageId);
            const data = await fetch(url, { headers: { Accept: 'application/json' } }).then(async response => {
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Riwayat sebelumnya gagal dimuat.');
                return payload;
            });
            button.insertAdjacentHTML('afterend', data.html);
            if (data.has_more) {
                button.disabled = false;
                button.textContent = 'Muat percakapan sebelumnya';
            } else {
                button.remove();
            }
        } catch (exception) {
            button.disabled = false;
            button.textContent = 'Coba muat lagi';
            const error = root.querySelector('[data-ai-error]');
            const text = error.querySelector('[data-ai-error-text]') ?? error;
            text.textContent = exception.message;
            error.hidden = false;
        }
    });
}

async function waitForRun(root, runId) {
    const deadline = Date.now() + 360_000;
    const url = root.dataset.runUrlTemplate.replace('__RUN__', runId);
    let consecutiveFailures = 0;

    while (Date.now() < deadline) {
        let data;
        try {
            data = await getJson(url);
            consecutiveFailures = 0;
        } catch (exception) {
            consecutiveFailures++;
            if (consecutiveFailures >= 3) throw exception;
            await new Promise(resolve => window.setTimeout(resolve, 1200));
            continue;
        }
        if (data.status === 'success') return data;
        if (data.status === 'cancelled') {
            const exception = new Error(data.message || 'Proses AI dihentikan.');
            exception.name = 'AbortError';
            throw exception;
        }
        if (data.status === 'failed') throw new Error(data.message || 'Proses AI gagal diselesaikan.');
        const activeStep = data.steps?.at(-1);
        const thinkingCopy = root.querySelector('[data-ai-thinking-indicator] [data-ai-thinking-copy]');
        if (thinkingCopy) {
            thinkingCopy.textContent = activeStep?.kind === 'tool_call' ? activeStep.label : 'Thinking';
        }
        await new Promise(resolve => window.setTimeout(resolve, 1200));
    }

    throw new Error('Proses AI belum selesai setelah beberapa menit. Periksa kembali percakapan ini sesaat lagi.');
}

function initThinkingControl(root) {
    const control = root.querySelector('[data-ai-thinking]');
    if (!control) return;

    const summary = control.querySelector('summary');
    const label = control.querySelector('[data-ai-thinking-label]');
    const status = control.querySelector('[data-ai-thinking-status]');
    const options = [...control.querySelectorAll('[data-ai-thinking-option]')];
    let currentValue = control.dataset.currentEffort;
    let saving = false;

    const syncSelection = value => {
        root.querySelectorAll('input[name="thinking_effort"]').forEach(input => {
            input.checked = input.value === value;
        });
    };

    options.forEach(option => option.addEventListener('change', async () => {
        if (!option.checked || saving || option.value === currentValue) return;

        const previousValue = currentValue;
        saving = true;
        control.setAttribute('aria-busy', 'true');
        options.forEach(input => { input.disabled = true; });
        status.removeAttribute('data-error');
        status.textContent = 'Menyimpan pilihan…';

        try {
            const data = await sendJson(root.dataset.thinkingUrl, 'PATCH', {
                thinking_effort: option.value,
            });
            currentValue = data.thinking_effort;
            control.dataset.currentEffort = currentValue;
            label.textContent = data.label;
            summary.setAttribute('aria-label', `Atur tingkat thinking, saat ini ${data.label}`);
            syncSelection(currentValue);
            status.textContent = 'Pilihan tersimpan.';
        } catch (exception) {
            syncSelection(previousValue);
            status.dataset.error = 'true';
            status.textContent = exception.message;
        } finally {
            saving = false;
            control.setAttribute('aria-busy', 'false');
            options.forEach(input => { input.disabled = false; });
        }
    }));

    document.addEventListener('click', event => {
        if (control.open && !control.contains(event.target)) control.removeAttribute('open');
    });
    control.addEventListener('keydown', event => {
        if (event.key === 'Escape' && control.open) {
            event.preventDefault();
            control.removeAttribute('open');
            summary.focus();
        }
    });
}

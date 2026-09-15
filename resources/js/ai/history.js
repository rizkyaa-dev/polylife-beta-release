import { sendJson } from './transport';

export function initHistory(workspaceUrl) {
    const sidebar = document.querySelector('[data-ai-sidebar]');
    if (!sidebar) return { update() {} };

    const search = sidebar.querySelector('[data-ai-history-search]');
    const list = sidebar.querySelector('[data-ai-history-list]');
    const empty = sidebar.querySelector('[data-ai-history-empty]');
    const noResults = sidebar.querySelector('[data-ai-history-no-results]');
    const status = sidebar.querySelector('[data-ai-history-action-status]');
    const template = document.querySelector('[data-ai-history-item-template]');
    const menu = document.querySelector('[data-ai-history-menu]');
    const sessionBaseUrl = sidebar.dataset.aiSessionBaseUrl;
    let menuItem = null;
    let menuTrigger = null;

    document.body.append(menu);

    const filter = () => {
        const term = search.value.trim().toLocaleLowerCase('id');
        let visible = 0;
        for (const item of list.children) {
            item.hidden = !item.dataset.sessionTitle.toLocaleLowerCase('id').includes(term);
            if (!item.hidden) visible++;
        }
        empty.hidden = list.children.length > 0;
        noResults.hidden = !term || visible > 0;
    };

    const closeMenu = (restoreFocus = false) => {
        if (!menuItem) return;
        menu.hidden = true;
        menuItem.removeAttribute('data-menu-open');
        menuTrigger.setAttribute('aria-expanded', 'false');
        const trigger = menuTrigger;
        menuItem = null;
        menuTrigger = null;
        if (restoreFocus) trigger.focus();
    };

    const positionMenu = () => {
        const triggerRect = menuTrigger.getBoundingClientRect();
        const gap = 8;
        const edge = 8;
        menu.hidden = false;
        const menuRect = menu.getBoundingClientRect();
        let left = triggerRect.right + gap;
        if (left + menuRect.width > window.innerWidth - edge) {
            left = Math.max(edge, triggerRect.left - menuRect.width - gap);
        }
        const top = Math.min(triggerRect.top, window.innerHeight - menuRect.height - edge);
        menu.style.left = `${left}px`;
        menu.style.top = `${Math.max(edge, top)}px`;
    };

    const openMenu = (item, trigger) => {
        if (menuItem === item) {
            closeMenu(true);
            return;
        }
        closeMenu();
        menuItem = item;
        menuTrigger = trigger;
        item.setAttribute('data-menu-open', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        positionMenu();
        menu.querySelector('button').focus();
    };

    const showStatus = (message, isError = false) => {
        status.textContent = message;
        status.dataset.error = isError ? 'true' : 'false';
        status.hidden = false;
    };

    const renameSession = async () => {
        const item = menuItem;
        const trigger = menuTrigger;
        const currentTitle = item.dataset.sessionTitle;
        const nextTitle = window.prompt('Ubah nama percakapan', currentTitle);
        if (nextTitle === null) return;

        const sessionId = item.dataset.sessionId;
        closeMenu();
        try {
            const data = await sendJson(`${sessionBaseUrl}/${sessionId}`, 'PATCH', { title: nextTitle });
            const title = data.session.title;
            item.dataset.sessionTitle = title;
            const link = item.querySelector('[data-ai-history-link]');
            link.title = title;
            link.querySelector('[data-ai-history-title]').textContent = title;
            trigger.setAttribute('aria-label', `Opsi untuk ${title}`);
            showStatus('Nama percakapan diperbarui.');
            filter();
        } catch (error) {
            showStatus(error.message, true);
        } finally {
            if (document.contains(trigger)) trigger.focus({ preventScroll: true });
        }
    };

    const deleteSession = async () => {
        const title = menuItem.dataset.sessionTitle;
        if (!window.confirm(`Hapus percakapan “${title}”? Tindakan ini tidak dapat dibatalkan.`)) return;

        const item = menuItem;
        const trigger = menuTrigger;
        const sessionId = item.dataset.sessionId;
        const isCurrent = item.dataset.active === 'true';
        closeMenu();
        try {
            await sendJson(`${sessionBaseUrl}/${sessionId}`, 'DELETE');
            item.remove();
            filter();
            if (isCurrent) {
                const url = new URL(workspaceUrl);
                url.searchParams.set('new', '1');
                window.location.assign(url);
                return;
            }
            showStatus('Percakapan dihapus.');
            search.focus({ preventScroll: true });
        } catch (error) {
            showStatus(error.message, true);
            trigger.focus({ preventScroll: true });
        }
    };

    search.addEventListener('input', filter);
    sidebar.addEventListener('click', event => {
        const trigger = event.target.closest('[data-ai-history-menu-trigger]');
        if (!trigger) return;
        event.preventDefault();
        openMenu(trigger.closest('[data-ai-history-item]'), trigger);
    });
    menu.addEventListener('click', async event => {
        const action = event.target.closest('[data-ai-history-action]')?.dataset.aiHistoryAction;
        if (action === 'rename') await renameSession();
        if (action === 'delete') await deleteSession();
    });
    document.addEventListener('pointerdown', event => {
        if (menuItem && !menu.contains(event.target) && !menuItem.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menuItem) closeMenu(true);
    });
    window.addEventListener('resize', () => closeMenu());
    sidebar.addEventListener('scroll', () => closeMenu(), true);

    return {
        update(sessionId, title) {
            const items = Array.from(list.children);
            let item = items.find(candidate => candidate.dataset.sessionId === String(sessionId));
            items.forEach(candidate => {
                candidate.dataset.active = 'false';
                candidate.querySelector('[data-ai-history-link]').removeAttribute('aria-current');
            });
            if (!item) {
                item = template.content.firstElementChild.cloneNode(true);
                item.dataset.sessionId = String(sessionId);
                item.querySelector('[data-ai-history-menu-trigger]').setAttribute('aria-label', `Opsi untuk ${title}`);
            }
            item.dataset.sessionTitle = title;
            item.dataset.active = 'true';
            const link = item.querySelector('[data-ai-history-link]');
            const url = new URL(workspaceUrl);
            url.searchParams.set('session', sessionId);
            link.href = url.href;
            link.title = title;
            link.setAttribute('aria-current', 'page');
            link.querySelector('[data-ai-history-title]').textContent = title;
            list.prepend(item);
            while (list.children.length > 15) list.lastElementChild.remove();
            filter();
        },
    };
}

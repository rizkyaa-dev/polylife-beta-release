/** Keep the latest turn visible while allowing the user to read older messages. */
export function createConversationScroller(viewport, content) {
    const bottomThreshold = 64;
    let following = true;
    let disposed = false;
    let frame = null;
    let lastScrollTop = viewport.scrollTop;

    const refresh = () => {
        if (disposed || !following || frame !== null) return;
        frame = globalThis.requestAnimationFrame(() => {
            frame = null;
            if (disposed || !following) return;
            viewport.scrollTop = viewport.scrollHeight;
            lastScrollTop = viewport.scrollTop;
        });
    };
    const onScroll = () => {
        if (viewport.scrollTop === lastScrollTop) return;
        lastScrollTop = viewport.scrollTop;
        following = viewport.scrollHeight - viewport.clientHeight - lastScrollTop <= bottomThreshold;
    };
    const observer = typeof globalThis.ResizeObserver === 'function'
        ? new globalThis.ResizeObserver(refresh) : null;
    observer?.observe(viewport);
    observer?.observe(content);
    viewport.addEventListener('scroll', onScroll, { passive: true });

    const dispose = () => {
        if (disposed) return;
        disposed = true;
        observer?.disconnect();
        viewport.removeEventListener('scroll', onScroll);
        globalThis.removeEventListener?.('pagehide', onPageHide);
        if (frame !== null) globalThis.cancelAnimationFrame(frame);
        frame = null;
    };
    const onPageHide = event => {
        // Cached pages reuse this controller when restored via browser history.
        if (!event.persisted) dispose();
    };
    globalThis.addEventListener?.('pagehide', onPageHide);

    return {
        refresh,
        scrollToLatest() {
            following = true;
            refresh();
        },
        preservePosition(mutate) {
            following = false;
            const top = viewport.scrollTop;
            const height = viewport.scrollHeight;
            mutate();
            viewport.scrollTop = top + viewport.scrollHeight - height;
            lastScrollTop = viewport.scrollTop;
        },
        dispose,
    };
}

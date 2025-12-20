@once
    @push('scripts')
        <script>
            window.buildReminderNotifier = () => {
                const notificationLog = new Map();
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const MILESTONES = [
                    { seconds: 24 * 3600, label: '1 hari lagi' },
                    { seconds: 3600, label: '1 jam lagi' },
                    { seconds: 300, label: '5 menit lagi' },
                    { seconds: 60, label: '1 menit lagi' },
                    { seconds: 0, label: 'Sudah sampai tenggat' },
                ];
                let permissionRequested = false;
                let swRegistration = null;
                let syncingPush = false;

                const pushConfig = {
                    vapidPublicKey: document.querySelector('meta[name="vapid-public-key"]')?.getAttribute('content') || '',
                    subscribeUrl: '{{ route('push.subscribe') }}',
                    unsubscribeUrl: '{{ route('push.unsubscribe') }}',
                    dashboardUrl: '{{ route('workspace.home') }}',
                };

                const isSupported = () => typeof window !== 'undefined' && 'Notification' in window;
                const ensureSet = (value) => value instanceof Set
                    ? value
                    : new Set(Array.isArray(value) ? value : []);

                const requestPermission = async () => {
                    if (!isSupported()) return false;
                    if (Notification.permission === 'granted') return true;
                    if (Notification.permission === 'denied') return false;
                    if (permissionRequested) return Notification.permission === 'granted';
                    permissionRequested = true;
                    try {
                        const result = await Notification.requestPermission();
                        return result === 'granted';
                    } catch (_) {
                        return false;
                    }
                };

                const urlBase64ToUint8Array = (base64String) => {
                    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);
                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }
                    return outputArray;
                };

                const registerServiceWorker = async () => {
                    if (!('serviceWorker' in navigator)) {
                        return null;
                    }
                    if (swRegistration) {
                        return swRegistration;
                    }
                    try {
                        swRegistration = await navigator.serviceWorker.register('/push-sw.js');
                        navigator.serviceWorker.addEventListener('message', (event) => {
                            if (event.data?.type === 'PUSH_SUBSCRIPTION_CHANGED') {
                                ensurePushSubscription();
                            }
                        });
                        return swRegistration;
                    } catch (error) {
                        console.warn('SW registration failed', error);
                        return null;
                    }
                };

                const syncSubscription = async (subscription) => {
                    if (!subscription || !pushConfig.subscribeUrl) return;
                    const payload = subscription.toJSON();
                    const contentEncoding = payload.keys?.auth ? 'aes128gcm' : 'aesgcm';
                    try {
                        await fetch(pushConfig.subscribeUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                endpoint: payload.endpoint,
                                keys: payload.keys,
                                content_encoding: contentEncoding,
                            }),
                        });
                    } catch (error) {
                        console.warn('Push subscribe sync failed', error);
                    }
                };

                const clearSubscription = async (registration) => {
                    const reg = registration || swRegistration || await registerServiceWorker();
                    if (!reg) return;
                    try {
                        const subscription = await reg.pushManager.getSubscription();
                        if (subscription) {
                            const endpoint = subscription.endpoint;
                            await subscription.unsubscribe();
                            if (endpoint && pushConfig.unsubscribeUrl) {
                                await fetch(pushConfig.unsubscribeUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken,
                                    },
                                    body: JSON.stringify({ endpoint }),
                                });
                            }
                        }
                    } catch (error) {
                        console.warn('Push unsubscribe failed', error);
                    }
                };

                const ensurePushSubscription = async () => {
                    if (syncingPush) return false;
                    if (!pushConfig.vapidPublicKey) return false;
                    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
                    if (Notification.permission === 'denied') {
                        await clearSubscription();
                        return false;
                    }

                    syncingPush = true;
                    try {
                        const permitted = Notification.permission === 'granted' || await requestPermission();
                        if (!permitted) {
                            await clearSubscription();
                            return false;
                        }

                        const registration = await registerServiceWorker();
                        if (!registration) return false;

                        let subscription = await registration.pushManager.getSubscription();
                        if (!subscription) {
                            subscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlBase64ToUint8Array(pushConfig.vapidPublicKey),
                            });
                        }

                        await syncSubscription(subscription);
                        return true;
                    } catch (error) {
                        console.warn('Push subscription failed', error);
                        return false;
                    } finally {
                        syncingPush = false;
                    }
                };

                const showNotification = async (title, options) => {
                    if (!isSupported()) return;
                    if (Notification.permission === 'denied') return;

                    const registration = await registerServiceWorker();
                    if (registration?.showNotification) {
                        try {
                            await registration.showNotification(title, options);
                            return;
                        } catch (error) {
                            console.warn('SW notification fallback to window', error);
                        }
                    }

                    new Notification(title, options);
                };

                const fireNotification = async (entry, milestoneSeconds) => {
                    if (!isSupported()) return;
                    const permitted = Notification.permission === 'granted'
                        || await requestPermission();
                    if (!permitted) return;

                    const milestone = MILESTONES.find((m) => m.seconds === milestoneSeconds);
                    const title = entry.title || 'Reminder';
                    const deadline = entry.deadlineText
                        ? `Tenggat: ${entry.deadlineText}`
                        : 'Sudah jatuh tempo';
                    const body = milestoneSeconds === 0
                        ? 'Waktu habis. Segera selesaikan atau perbarui.'
                        : `${milestone?.label ?? 'Pengingat'} sebelum tenggat.`;

                    await showNotification(title, {
                        body: `${body}\n${deadline}`,
                        icon: '/favicon.ico',
                        tag: `reminder-${entry.id}-${milestoneSeconds}`,
                        data: {
                            url: pushConfig.dashboardUrl,
                            reminderId: entry.id,
                            milestone: milestoneSeconds,
                        },
                    });
                };

                const handleCountdown = (entry, previousSeconds) => {
                    if (!entry || !entry.id) return;
                    entry.notified = ensureSet(entry.notified);
                    MILESTONES.forEach((milestone) => {
                        if (entry.notified.has(milestone.seconds)) {
                            return;
                        }
                        if (previousSeconds >= milestone.seconds && entry.seconds <= milestone.seconds) {
                            entry.notified.add(milestone.seconds);
                            notificationLog.set(entry.id, entry.notified);
                            fireNotification(entry, milestone.seconds);
                        }
                    });
                };

                const attachEntry = (entry) => {
                    const notified = notificationLog.get(entry.id) ?? ensureSet(entry.notified);
                    entry.notified = notified;
                    notificationLog.set(entry.id, notified);
                };

                const clear = () => notificationLog.clear();

                const requestPermissionIfNeeded = async (hasActiveReminder = false) => {
                    if (!hasActiveReminder) return;
                    if (Notification.permission === 'default') {
                        await requestPermission();
                    }
                    await ensurePushSubscription();
                };

                const armUserGestureHook = () => {
                    const handler = async () => {
                        document.removeEventListener('click', handler);
                        await ensurePushSubscription();
                    };
                    document.addEventListener('click', handler, { once: true, passive: true });
                };

                armUserGestureHook();

                return {
                    attachEntry,
                    handleCountdown,
                    clear,
                    requestPermissionIfNeeded,
                };
            };
        </script>
    @endpush
@endonce

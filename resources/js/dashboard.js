import Chart from 'chart.js/auto';
import { runWhenPageIsActive } from './support/page-activation';

window.Chart = window.Chart || Chart;

const dashboardConfig = window.PolyLifeDashboard || {};
const initDashboardInteractive = () => {
    const isGuestMode = document.body?.dataset?.guestMode === '1';
    if (isGuestMode) {
        return;
    }
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const todoRemovalTimers = new Map();
    const reminderCountdowns = new Map();

    const escapeHtml = (value = '') => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const cancelRemoval = (card) => {
        if (!card) return;
        const key = card.dataset.todoId;
        if (!key) return;
        if (todoRemovalTimers.has(key)) {
            clearTimeout(todoRemovalTimers.get(key));
            todoRemovalTimers.delete(key);
        }
    };

    const scheduleRemoval = (card, seconds) => {
        if (!card) return;
        const key = card.dataset.todoId;
        if (!key || !seconds || seconds <= 0) return;
        cancelRemoval(card);
        const timerId = setTimeout(() => {
            card.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => card.remove(), 300);
        }, seconds * 1000);
        todoRemovalTimers.set(key, timerId);
    };

    document.querySelectorAll('[data-todo-card]').forEach((card) => {
        const removeAfter = Number(card.dataset.removeAfter ?? 0);
        if (removeAfter > 0) {
            scheduleRemoval(card, removeAfter);
        }
    });

    const toggleTodo = async (checkbox) => {
        const url = checkbox.dataset.toggleUrl;
        const card = checkbox.closest('[data-todo-card]');
        const textEl = card?.querySelector('[data-todo-text]');
        const metaEl = card?.querySelector('[data-todo-meta]');
        const isChecked = checkbox.checked;

        if (!url || !csrfToken) {
            checkbox.checked = !isChecked;
            return;
        }

        checkbox.disabled = true;
        card?.classList.add('opacity-70');

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ status: isChecked ? 1 : 0 }),
            });
            if (!response.ok) {
                throw new Error('Gagal memperbarui status to-do.');
            }
            const payload = await response.json();

            textEl?.classList.toggle('line-through', payload.status);
            textEl?.classList.toggle('text-gray-400', payload.status);
            textEl?.classList.toggle('text-gray-800', !payload.status);

            if (metaEl) {
                metaEl.textContent = payload.meta ?? (payload.status
                    ? 'Ditandai selesai - akan hilang dalam 10 menit.'
                    : 'Centang untuk menandai selesai.');
            }

            if (card) {
                if (payload.status) {
                    const seconds = Number(payload.visible_for_seconds ?? 600);
                    card.dataset.removeAfter = String(seconds);
                    card.classList.remove('opacity-0', 'pointer-events-none');
                    scheduleRemoval(card, seconds);
                } else {
                    card.dataset.removeAfter = '';
                    cancelRemoval(card);
                    card.classList.remove('opacity-0', 'pointer-events-none');
                }
            }
        } catch (error) {
            checkbox.checked = !isChecked;
            alert(error.message ?? 'Terjadi kesalahan saat memperbarui to-do.');
        } finally {
            checkbox.disabled = false;
            card?.classList.remove('opacity-70');
        }
    };

    document.querySelectorAll('[data-todo-toggle]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => toggleTodo(checkbox));
    });

    const reminderListEl = document.querySelector('[data-reminder-list]');
    const reminderEmptyEl = document.getElementById('reminderEmptyState');
    const reminderEndpoint = dashboardConfig.reminderEndpoint || '';
    let reminderInterval = null;
    let reminderTickInterval = null;
    const REMINDER_BADGE_BASE = 'inline-flex items-center gap-2 rounded-lg px-3 py-1 text-xs font-semibold border';
    const REMINDER_DOT_BASE = 'reminder-dot h-2.5 w-2.5 rounded-full';
    const reminderNotifier = window.buildReminderNotifier
        ? window.buildReminderNotifier()
        : null;

    const resetReminderCountdowns = () => {
        reminderCountdowns.clear();
    };

    const formatTimeLeftText = (seconds) => {
        if (seconds <= 0) return 'Segera jatuh tempo';

        const minute = 60;
        const hour = 60 * minute;
        const day = 24 * hour;
        const week = 7 * day;

        const weeks = Math.floor(seconds / week);
        const days = Math.floor(seconds / day) % 7;
        const hours = Math.floor(seconds / hour) % 24;
        const minutes = Math.floor(seconds / minute) % 60;

        const parts = [];
        if (weeks > 0) parts.push(`${weeks} minggu`);
        if (days > 0 && parts.length < 2) parts.push(`${days} hari`);
        if (weeks === 0 && hours > 0 && parts.length < 2) parts.push(`${hours} jam`);
        if (weeks === 0 && days === 0 && minutes > 0 && parts.length < 2) parts.push(`${minutes} menit`);
        if (!parts.length) parts.push('kurang dari 1 menit');

        return `Sisa ${parts.join(' ')}`;
    };

    const getReminderUrgencyState = (seconds) => {
        const oneDay = 24 * 3600;
        const oneWeek = 7 * oneDay;
        const threeHours = 3 * 3600;

        if (seconds >= oneWeek) {
            return {
                badge: 'bg-green-50 text-green-700 border-green-100',
                dot: 'bg-green-400',
                blink: false,
            };
        }

        if (seconds >= oneDay) {
            return {
                badge: 'bg-amber-50 text-amber-700 border-amber-200',
                dot: 'bg-amber-400',
                blink: false,
            };
        }

        if (seconds >= threeHours) {
            return {
                badge: 'bg-rose-50 text-rose-700 border-rose-200',
                dot: 'bg-rose-400',
                blink: false,
            };
        }

        return {
            badge: 'bg-rose-700 text-white border-black dark:border-white',
            dot: 'reminder-dot-critical',
            blink: true,
        };
    };

    const applyReminderVisual = (entry) => {
        const { badgeEl, dotEl, timeTextEl } = entry;
        const seconds = entry.seconds;
        const state = getReminderUrgencyState(seconds);
        if (badgeEl) {
            badgeEl.className = `${REMINDER_BADGE_BASE} ${state.badge}`;
            badgeEl.classList.toggle('reminder-blink', state.blink);
        }
        if (dotEl) {
            dotEl.className = `${REMINDER_DOT_BASE} ${state.dot}`;
        }
        if (timeTextEl) {
            timeTextEl.textContent = formatTimeLeftText(seconds);
        }
    };

    const updateReminderCountdowns = () => {
        reminderCountdowns.forEach((entry) => {
            const previousSeconds = entry.seconds;
            entry.seconds = Math.max(0, entry.seconds - 1);
            if (reminderNotifier) {
                reminderNotifier.handleCountdown(entry, previousSeconds);
            }
            applyReminderVisual(entry);
        });
    };

    const ensureReminderTick = () => {
        if (!reminderTickInterval) {
            reminderTickInterval = setInterval(updateReminderCountdowns, 1000);
        }
    };

    const registerReminderItems = () => {
        resetReminderCountdowns();
        let hasItems = false;
        document.querySelectorAll('[data-reminder-item]').forEach((itemEl) => {
            const id = itemEl.dataset.reminderId;
            if (!id) return;
            hasItems = true;
            const seconds = Number(itemEl.dataset.secondsLeft ?? 0);
            const badgeEl = itemEl.querySelector('[data-reminder-badge]');
            const dotEl = itemEl.querySelector('[data-reminder-dot]');
            const timeTextEl = itemEl.querySelector('[data-reminder-timeleft]');
            const title = itemEl.dataset.reminderTitle || 'Reminder';
            const deadlineText = itemEl.dataset.reminderDeadline || '';
            const entry = {
                id,
                itemEl,
                badgeEl,
                dotEl,
                timeTextEl,
                seconds: Number.isFinite(seconds) ? seconds : 0,
                title,
                deadlineText,
                notified: new Set(),
            };
            reminderCountdowns.set(id, entry);
            if (reminderNotifier) {
                reminderNotifier.attachEntry(entry);
                reminderNotifier.handleCountdown(entry, entry.seconds + 1);
            }
            applyReminderVisual(entry);
        });
        if (reminderNotifier) {
            reminderNotifier.requestPermissionIfNeeded(hasItems);
        }
        ensureReminderTick();
    };

    const renderReminders = (items = []) => {
        if (!reminderListEl) return;
        if (!items.length) {
            reminderListEl.innerHTML = '';
            reminderEmptyEl?.classList.remove('hidden');
            resetReminderCountdowns();
            reminderNotifier?.clear();
            return;
        }

        const html = items.map((item) => {
            const badgeClasses = `${item.badge_classes ?? ''} ${item.blink ? 'reminder-blink' : ''}`;
            const dotClasses = `reminder-dot h-2.5 w-2.5 rounded-full ${item.dot_classes ?? ''}`;
            const timeLeftText = escapeHtml(item.time_left_text ?? 'Sisa waktu tidak diketahui');
            const waktuFormatted = escapeHtml(item.waktu_formatted ?? '');
            const timeDiff = escapeHtml(item.time_diff ?? '');
            const title = escapeHtml(item.title ?? 'Reminder');
            const editUrl = escapeHtml(item.edit_url ?? '#');

            return `
                <li class="py-3 space-y-2"
                    data-reminder-item
                    data-reminder-id="${escapeHtml(item.id ?? '')}"
                    data-seconds-left="${Number(item.seconds_left ?? 0)}"
                    data-reminder-title="${title}"
                    data-reminder-deadline="${waktuFormatted}">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium text-gray-800">${title}</p>
                            <p class="text-sm text-gray-500">Tenggat: ${waktuFormatted}</p>
                        </div>
                        <a href="${editUrl}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="${REMINDER_BADGE_BASE} ${badgeClasses}" data-reminder-badge>
                            <span class="${REMINDER_DOT_BASE} ${dotClasses}" data-reminder-dot></span>
                            <span data-reminder-timeleft>${timeLeftText}</span>
                        </span>
                    </div>
                </li>
            `;
        }).join('');

        reminderListEl.innerHTML = html;
        reminderEmptyEl?.classList.add('hidden');
        registerReminderItems();
    };

    const fetchReminders = async () => {
        if (!reminderListEl) return;
        try {
            const response = await fetch(reminderEndpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) throw new Error('Gagal memperbarui reminder.');
            const payload = await response.json();
            renderReminders(payload.data ?? []);
        } catch (error) {
            console.warn(error.message ?? error);
        }
    };

    const startReminderInterval = () => {
        if (reminderInterval || !reminderListEl) return;
        reminderInterval = setInterval(fetchReminders, 30000);
    };

    const stopReminderInterval = () => {
        if (!reminderInterval) return;
        clearInterval(reminderInterval);
        reminderInterval = null;
    };

    if (reminderListEl) {
        registerReminderItems();
        fetchReminders();
        startReminderInterval();
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopReminderInterval();
            } else {
                fetchReminders();
                startReminderInterval();
            }
        });
    }

    const ctx = document.getElementById('chartKeuanganPie');
    if (!ctx) return;

    const chartLoading = document.getElementById('chartLoading');
    const statButtons = document.querySelectorAll('.stat-card');
    const bulanSelector = document.getElementById('bulanKeuanganSelect');
    const selectedBulan = bulanSelector?.value ?? '';
    const statEls = {
        pemasukan: document.getElementById('statPemasukan'),
        pengeluaran: document.getElementById('statPengeluaran'),
        saldo: document.getElementById('statSaldo'),
    };

    const formatRupiah = (angka) => 'Rp ' + new Intl.NumberFormat('id-ID').format(angka);

    const updateStatText = (el, value) => {
        if (!el) return;
        el.dataset.raw = value;
        el.textContent = formatRupiah(value);
    };

    const saldoLiquid = document.getElementById('saldoLiquid');
    const saldoWindow = document.getElementById('saldoWindow');
    const saldoText = document.getElementById('saldoIndicatorValue');
    const saldoCard = document.getElementById('saldoCard');
    const saldoLabelWrap = document.getElementById('saldoLabelWrap');
    const saldoSubtitle = document.getElementById('saldoSubtitle');
    const labelSaldo = document.getElementById('labelSaldo');

    const updateSaldoIndicator = (saldoValue, baseline) => {
        if (!saldoLiquid || !saldoWindow || !saldoText) return;

        const rawReference = Math.max(Math.abs(baseline), Math.abs(saldoValue));
        const rawRatio = rawReference === 0 ? 0 : Math.abs(saldoValue) / rawReference;
        // Keep a minimal wave for non-zero values, but allow a true 0% indicator.
        const displayRatio = rawRatio === 0 ? 0 : Math.max(0.05, rawRatio);
        saldoLiquid.style.height = `${displayRatio * 100}%`;

        const positiveGradient = 'linear-gradient(180deg, rgba(129,140,248,0.95) 0%, rgba(59,130,246,0.85) 80%)';
        const negativeGradient = 'linear-gradient(180deg, rgba(15,23,42,0.95) 0%, rgba(0,0,0,0.9) 80%)';
        const isPositive = saldoValue >= 0;

        saldoLiquid.style.background = isPositive ? positiveGradient : negativeGradient;
        saldoWindow.classList.toggle('ring-4', !isPositive);
        saldoWindow.classList.toggle('ring-rose-200', !isPositive);
        saldoWindow.classList.toggle('ring-indigo-200', isPositive);
        saldoText.classList.toggle('text-indigo-800', isPositive);
        saldoText.classList.toggle('text-red-500', !isPositive);
        const percent = Math.round(rawRatio * 100) * (saldoValue >= 0 ? 1 : -1);
        saldoText.textContent = `${percent}%`;
    };

    const applySaldoVisualState = (isNegative) => {
        if (saldoCard) {
            saldoCard.classList.toggle('bg-gray-900', isNegative);
            saldoCard.classList.toggle('border-gray-900', isNegative);
            saldoCard.classList.toggle('text-white', isNegative);
            saldoCard.classList.toggle('text-indigo-800', !isNegative);
            saldoCard.classList.toggle('bg-indigo-50', !isNegative);
            saldoCard.classList.toggle('border-indigo-100', !isNegative);
            saldoCard.classList.toggle('focus-visible:ring-gray-700', isNegative);
            saldoCard.classList.toggle('focus-visible:ring-indigo-400', !isNegative);
        }
        saldoLabelWrap?.classList.toggle('text-white', isNegative);
        saldoLabelWrap?.classList.toggle('text-indigo-700', !isNegative);
        statEls.saldo?.classList.toggle('text-white', isNegative);
        statEls.saldo?.classList.toggle('text-indigo-800', !isNegative);
        saldoSubtitle?.classList.toggle('text-gray-200', isNegative);
        saldoSubtitle?.classList.toggle('text-indigo-700/70', !isNegative);
    };

    const loadChartJs = () => Promise.resolve();

    const sliceIndexMap = {
        pemasukan: 0,
        pengeluaran: 1,
    };

    let pieChart = null;
    let saldoIndicatorHidden = false;
    let updateInterval = null;

    const baseEndpoint = dashboardConfig.keuanganEndpoint || '';
    const endpoint = selectedBulan ? `${baseEndpoint}?bulan=${encodeURIComponent(selectedBulan)}` : baseEndpoint;

    let latestData = {
        pemasukan: Number(statEls.pemasukan?.dataset.raw ?? 0),
        pengeluaran: Number(statEls.pengeluaran?.dataset.raw ?? 0),
        saldo: Number(statEls.saldo?.dataset.raw ?? 0),
    };

    const updateSliceVisibility = () => {
        statButtons.forEach((btn) => {
            if (btn.dataset.slice === 'saldo') {
                const isHidden = saldoIndicatorHidden;
                btn.classList.toggle('opacity-60', isHidden);
                btn.classList.toggle('ring-2', !isHidden);
                btn.classList.toggle('ring-offset-2', !isHidden);
                saldoWindow?.classList.toggle('opacity-40', isHidden);
                saldoText?.classList.toggle('opacity-50', isHidden);
                saldoLiquid?.classList.toggle('opacity-50', isHidden);
                return;
            }

            const slice = btn.dataset.slice;
            const index = sliceIndexMap[slice];
            const isHidden = pieChart
                ? (pieChart.getDatasetMeta(0).data[index]?.hidden ?? false)
                : false;
            btn.classList.toggle('opacity-60', isHidden);
            btn.classList.toggle('ring-2', !isHidden);
            btn.classList.toggle('ring-offset-2', !isHidden);
        });
    };

    const toggleSlice = (sliceKey) => {
        if (sliceKey === 'saldo') {
            saldoIndicatorHidden = !saldoIndicatorHidden;
            updateSliceVisibility();
            return;
        }

        if (!pieChart) {
            return;
        }
        const index = sliceIndexMap[sliceKey];
        if (typeof index === 'undefined') {
            return;
        }
        const meta = pieChart.getDatasetMeta(0).data[index];
        if (!meta) {
            return;
        }
        meta.hidden = !meta.hidden;
        pieChart.update();
        updateSliceVisibility();
    };

    const applyFinancialDataToUi = () => {
        updateStatText(statEls.pemasukan, latestData.pemasukan);
        updateStatText(statEls.pengeluaran, latestData.pengeluaran);
        updateStatText(statEls.saldo, latestData.saldo);
        updateSaldoIndicator(latestData.saldo, latestData.pemasukan);

        const isNegative = latestData.saldo < 0;
        if (labelSaldo && saldoSubtitle) {
            labelSaldo.textContent = isNegative ? 'Hutang' : 'Saldo';
            saldoSubtitle.textContent = `${isNegative ? 'Total hutang' : 'Sisa dana'} per ${dashboardConfig.currentDateLabel || ''}`;
        }
        applySaldoVisualState(isNegative);
    };

    const applyFinancialDataToChart = () => {
        if (!pieChart) {
            return;
        }
        pieChart.data.datasets[0].data = [latestData.pemasukan, latestData.pengeluaran];
        pieChart.update('none');
    };

    const fetchAndUpdate = async () => {
        try {
            const response = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) throw new Error('Gagal mengambil data terbaru');
            const payload = await response.json();
            const data = payload?.data ?? {};

            latestData = {
                pemasukan: Number(data.total_pemasukan ?? 0),
                pengeluaran: Number(data.total_pengeluaran ?? 0),
                saldo: Number(data.saldo_bulan_ini ?? 0),
            };

            applyFinancialDataToUi();
            applyFinancialDataToChart();
            updateSliceVisibility();
        } catch (error) {
            console.warn(error.message ?? error);
        }
    };

    const startInterval = () => {
        if (updateInterval) return;
        updateInterval = setInterval(fetchAndUpdate, 30000);
    };

    const clearIntervalIfNeeded = () => {
        if (!updateInterval) return;
        clearInterval(updateInterval);
        updateInterval = null;
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearIntervalIfNeeded();
        } else {
            fetchAndUpdate();
            startInterval();
        }
    });

    statButtons.forEach((btn) => {
        btn.addEventListener('click', () => toggleSlice(btn.dataset.slice));
    });

    const initializeIndicators = () => {
        applyFinancialDataToUi();
        updateSliceVisibility();
    };

    initializeIndicators();
    fetchAndUpdate();
    startInterval();

    loadChartJs()
        .then(() => {
            chartLoading?.classList.add('hidden');
            pieChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Pemasukan', 'Pengeluaran'],
                    datasets: [{
                        data: [
                            Number(statEls.pemasukan?.dataset.raw ?? 0),
                            Number(statEls.pengeluaran?.dataset.raw ?? 0),
                        ],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgba(34, 197, 94, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false },
                    }
                }
            });
            applyFinancialDataToChart();
            updateSliceVisibility();
        })
        .catch((error) => {
            console.error('Gagal memuat Chart.js:', error);
            chartLoading?.classList.add('hidden');
        });
};

runWhenPageIsActive(initDashboardInteractive);

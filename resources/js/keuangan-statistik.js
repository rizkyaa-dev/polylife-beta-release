import Chart from 'chart.js/auto';

const chartDataElement = document.getElementById('keuangan-statistik-data');
const printButton = document.querySelector('[data-statistik-print]');
const printLabel = document.querySelector('[data-statistik-print-label]');
const chartCanvas = document.getElementById('chartKeuangan');

let chart = null;

const enablePrint = () => {
    if (!printButton) {
        return;
    }

    printButton.disabled = false;
    printButton.removeAttribute('aria-disabled');
    if (printLabel) {
        printLabel.textContent = 'Export PDF';
    }
};

const parseChartPayload = () => {
    if (!chartDataElement) {
        return null;
    }

    try {
        return JSON.parse(chartDataElement.textContent || '{}');
    } catch (error) {
        console.warn('Payload statistik keuangan tidak valid.', error);

        return null;
    }
};

const numericSeries = (value) => Array.isArray(value)
    ? value.map((item) => Number(item) || 0)
    : [];

const isDarkMode = () => {
    const root = document.documentElement;
    return root.classList.contains('dark') || root.dataset.theme === 'dark';
};

const getThemeColors = () => {
    const dark = isDarkMode();

    return {
        textColor: dark ? '#94a3b8' : '#64748b', // slate-400 vs slate-500
        titleColor: dark ? '#e2e8f0' : '#334155', // slate-200 vs slate-700
        gridColor: dark ? 'rgba(51, 65, 85, 0.5)' : 'rgba(226, 232, 240, 0.8)', // slate-700 vs slate-200
        legendColor: dark ? '#cbd5e1' : '#334155',
    };
};

const applyChartTheme = (chartInstance) => {
    if (!chartInstance) {
        return;
    }

    const colors = getThemeColors();

    if (chartInstance.options?.scales?.x) {
        chartInstance.options.scales.x.ticks = chartInstance.options.scales.x.ticks || {};
        chartInstance.options.scales.x.ticks.color = colors.textColor;
        chartInstance.options.scales.x.grid = chartInstance.options.scales.x.grid || {};
        chartInstance.options.scales.x.grid.color = colors.gridColor;
    }

    if (chartInstance.options?.scales?.y) {
        chartInstance.options.scales.y.ticks = chartInstance.options.scales.y.ticks || {};
        chartInstance.options.scales.y.ticks.color = colors.textColor;
        chartInstance.options.scales.y.grid = chartInstance.options.scales.y.grid || {};
        chartInstance.options.scales.y.grid.color = colors.gridColor;
        chartInstance.options.scales.y.title = chartInstance.options.scales.y.title || {};
        chartInstance.options.scales.y.title.color = colors.titleColor;
    }

    if (chartInstance.options?.scales?.y1) {
        chartInstance.options.scales.y1.ticks = chartInstance.options.scales.y1.ticks || {};
        chartInstance.options.scales.y1.ticks.color = colors.textColor;
        chartInstance.options.scales.y1.title = chartInstance.options.scales.y1.title || {};
        chartInstance.options.scales.y1.title.color = colors.titleColor;
    }

    if (chartInstance.options?.plugins?.legend) {
        chartInstance.options.plugins.legend.labels = chartInstance.options.plugins.legend.labels || {};
        chartInstance.options.plugins.legend.labels.color = colors.legendColor;
    }

    chartInstance.update('none');
};

const renderChart = () => {
    const payload = parseChartPayload();
    if (!chartCanvas || !payload) {
        enablePrint();
        return;
    }

    const colors = getThemeColors();

    try {
        chart = new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: Array.isArray(payload.labels) ? payload.labels : [],
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: numericSeries(payload.pemasukan),
                        backgroundColor: '#10b981',
                    },
                    {
                        label: 'Pengeluaran',
                        data: numericSeries(payload.pengeluaran),
                        backgroundColor: '#ef4444',
                    },
                    {
                        type: 'line',
                        label: 'Netto',
                        data: numericSeries(payload.net),
                        borderColor: '#6366f1',
                        backgroundColor: 'transparent',
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: 'Saldo Kumulatif',
                        data: numericSeries(payload.saldo),
                        borderColor: '#f59e0b',
                        backgroundColor: 'transparent',
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                animation: false,
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                stacked: false,
                plugins: {
                    legend: {
                        labels: {
                            color: colors.legendColor,
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: colors.textColor },
                        grid: { color: colors.gridColor },
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Nominal (Rp)', color: colors.titleColor },
                        ticks: { color: colors.textColor },
                        grid: { color: colors.gridColor },
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Saldo', color: colors.titleColor },
                        ticks: { color: colors.textColor },
                    },
                },
            },
        });
    } catch (error) {
        console.error('Gagal merender chart statistik keuangan:', error);
    } finally {
        requestAnimationFrame(enablePrint);
    }
};

const setupThemeObserver = () => {
    const root = document.documentElement;
    const observer = new MutationObserver(() => {
        if (chart) {
            applyChartTheme(chart);
        }
    });

    observer.observe(root, {
        attributes: true,
        attributeFilter: ['class', 'data-theme'],
    });
};

const bindPrint = () => {
    if (!printButton) {
        return;
    }

    printButton.setAttribute('aria-disabled', 'true');
    printButton.addEventListener('click', () => {
        if (printButton.disabled) {
            return;
        }

        requestAnimationFrame(() => window.print());
    });
};

window.addEventListener('beforeprint', () => {
    chart?.resize();
});

window.addEventListener('afterprint', () => {
    chart?.resize();
});

bindPrint();
renderChart();
setupThemeObserver();


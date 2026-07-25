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

const renderChart = () => {
    const payload = parseChartPayload();
    if (!chartCanvas || !payload) {
        enablePrint();
        return;
    }

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
                    borderColor: '#4f46e5',
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
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Nominal (Rp)' },
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Saldo' },
                },
            },
        },
    });

    requestAnimationFrame(enablePrint);
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

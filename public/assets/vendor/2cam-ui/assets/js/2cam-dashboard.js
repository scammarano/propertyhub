(function () {
    'use strict';
    function payload() {
        const node = document.getElementById('cam-dashboard-data');
        if (!node) return null;
        try { return JSON.parse(node.textContent || '{}'); } catch (_) { return null; }
    }
    function css(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }
    function money(value, currency) {
        try { return new Intl.NumberFormat('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Number(value || 0)) + ' ' + currency; }
        catch (_) { return Number(value || 0).toFixed(2) + ' ' + currency; }
    }
    function compact(value) {
        try { return new Intl.NumberFormat('es-ES', { notation: 'compact', maximumFractionDigits: 1 }).format(Number(value || 0)); }
        catch (_) { return String(value || 0); }
    }
    function init() {
        const data = payload();
        if (!data || typeof window.Chart !== 'function') return;
        const currency = data.currency || 'EUR';
        const primary = css('--primary', '#5a40d6');
        const text = css('--cam-text', css('--ink', '#23273a'));
        const muted = css('--cam-muted', css('--muted', '#6f7585'));
        const border = css('--cam-border', css('--border', '#e4e7ee'));
        const surface = css('--cam-surface', '#ffffff');
        const palette = Array.from({length: 8}, (_, i) => css('--chart-' + (i + 1), ['#5a40d6','#1aa374','#e2a20f','#dc4b55','#087ea4','#9b59b6','#64748b','#ea580c'][i]));
        const showLabels = document.body.dataset.chartLabels === '1';

        Chart.defaults.global.defaultFontFamily = 'Inter, Aptos, Segoe UI, Arial, sans-serif';
        Chart.defaults.global.defaultFontColor = muted;
        Chart.defaults.global.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 450;
        Chart.defaults.global.legend.labels.usePointStyle = true;

        const valuePlugin = {
            afterDatasetsDraw(chart) {
                if (!showLabels || chart.config.type !== 'bar') return;
                const ctx = chart.ctx;
                ctx.save();
                ctx.font = '700 10px Inter, Segoe UI, sans-serif';
                ctx.fillStyle = text;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    if (meta.hidden) return;
                    meta.data.forEach((element, index) => {
                        const value = Number(dataset.data[index] || 0);
                        if (value <= 0) return;
                        const model = element._model || element._view;
                        ctx.fillText(compact(value), model.x, Math.max(12, model.y - 5));
                    });
                });
                ctx.restore();
            }
        };

        const revenue = document.getElementById('cam-revenue-chart');
        if (revenue && data.revenue) {
            new Chart(revenue.getContext('2d'), {
                type: 'bar',
                plugins: [valuePlugin],
                data: {
                    labels: data.revenue.labels || [],
                    datasets: [{
                        label: 'Ingresos',
                        data: data.revenue.values || [],
                        backgroundColor: primary + 'cc',
                        borderColor: primary,
                        borderWidth: 1,
                        hoverBackgroundColor: primary,
                        maxBarThickness: 42
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    layout: { padding: { top: showLabels ? 18 : 4 } },
                    tooltips: { callbacks: { label: item => money(item.yLabel, currency) } },
                    scales: {
                        xAxes: [{ gridLines: { display: false }, ticks: { fontColor: muted } }],
                        yAxes: [{ gridLines: { color: border, drawBorder: false }, ticks: { beginAtZero: true, fontColor: muted, callback: value => compact(value) } }]
                    }
                }
            });
        }

        const expenses = document.getElementById('cam-expense-chart');
        if (expenses && data.expenses && (data.expenses.values || []).length) {
            new Chart(expenses.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: data.expenses.labels || [],
                    datasets: [{ data: data.expenses.values || [], backgroundColor: palette, borderWidth: 2, borderColor: surface, hoverBorderColor: surface }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutoutPercentage: 66,
                    legend: { display: false },
                    tooltips: { callbacks: { label: (item, chartData) => chartData.labels[item.index] + ': ' + money(chartData.datasets[0].data[item.index], currency) } }
                }
            });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();

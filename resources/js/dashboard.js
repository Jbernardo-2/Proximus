document.querySelectorAll('[data-role-dashboard]').forEach((dashboard) => {
    const filterButtons = dashboard.querySelectorAll('[data-work-filter]');
    const workItems = dashboard.querySelectorAll('[data-work-item]');

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.workFilter;

            filterButtons.forEach((candidate) => {
                const selected = candidate === button;
                candidate.setAttribute('aria-pressed', String(selected));
                candidate.classList.toggle('btn-primary', selected);
                candidate.classList.toggle('btn-secondary', ! selected);
            });
            workItems.forEach((item) => {
                item.classList.toggle('hidden', filter !== 'all' && item.dataset.workItem !== filter);
            });
            dashboard.querySelectorAll('[data-work-section]').forEach((section) => {
                const visibleItems = [...section.querySelectorAll('[data-work-item]')]
                    .some((item) => ! item.classList.contains('hidden'));
                const hasItems = section.querySelector('[data-work-item]') !== null;
                section.querySelector('[data-filter-empty]')?.classList.toggle('hidden', visibleItems || ! hasItems);
            });
        });
    });

    const chart = dashboard.querySelector('[data-dashboard-chart]');

    if (! chart) {
        return;
    }

    const datasets = {
        orders: chart.dataset.orders.split(',').map(Number),
        sales: chart.dataset.sales.split(',').map(Number),
    };
    const bars = chart.querySelectorAll('[data-chart-bar]');
    const values = chart.querySelectorAll('[data-chart-value]');
    const modeButtons = chart.querySelectorAll('[data-chart-mode]');
    const money = new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' });

    modeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.dataset.chartMode;
            const dataset = datasets[mode];
            const maximum = Math.max(...dataset, 1);

            modeButtons.forEach((candidate) => {
                const selected = candidate === button;
                candidate.setAttribute('aria-pressed', String(selected));
                candidate.classList.toggle('bg-ink-950', selected);
                candidate.classList.toggle('text-white', selected);
            });
            bars.forEach((bar, index) => {
                bar.style.height = `${Math.max(5, (dataset[index] / maximum) * 100)}%`;
                bar.classList.toggle('bg-leaf-500', mode === 'orders');
                bar.classList.toggle('bg-sky-500', mode === 'sales');
                values[index].textContent = mode === 'sales' ? money.format(dataset[index]) : dataset[index];
            });
        });
    });
});

document.querySelectorAll('[data-trend-chart]').forEach((chart) => {
    const readout = chart.querySelector('[data-trend-readout]');

    chart.querySelectorAll('[data-trend-day]').forEach((day) => {
        day.addEventListener('pointerenter', () => {
            readout.textContent = day.dataset.trendDay;
        });
        day.addEventListener('pointerleave', () => {
            readout.textContent = chart.dataset.defaultReadout;
        });
    });
});

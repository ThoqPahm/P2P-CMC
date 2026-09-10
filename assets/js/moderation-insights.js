(() => {
    const modal = document.getElementById('questionInsightsModal');
    const period = document.getElementById('insightPeriod');
    if (!modal || !period) return;

    const loading = modal.querySelector('.insight-loading');
    const results = modal.querySelector('.insight-results');
    const total = modal.querySelector('[data-insight-total]');
    const unanswered = modal.querySelectorAll('[data-insight-unanswered]');
    const rows = modal.querySelectorAll('[data-question-index]');
    const datasets = {
        7: { total: 38, unanswered: 3, counts: [7, 6, 5, 3], trends: ['+17%', '+12%', '+9%', '+6%'] },
        30: { total: 126, unanswered: 7, counts: [18, 15, 13, 9], trends: ['+38%', '+25%', '+18%', '+12%'] },
        90: { total: 341, unanswered: 16, counts: [46, 39, 34, 27], trends: ['+52%', '+41%', '+33%', '+24%'] },
    };
    let timer = 0;

    const applyDataset = () => {
        const data = datasets[period.value] || datasets[30];
        total.textContent = String(data.total);
        unanswered.forEach((item) => { item.textContent = String(data.unanswered); });
        rows.forEach((row, index) => {
            const count = row.querySelector('[data-repeat-count]');
            const trend = row.querySelector('[data-repeat-trend]');
            if (count) count.textContent = String(data.counts[index] ?? 0);
            if (trend) trend.textContent = data.trends[index] ?? '';
        });
    };

    const aggregate = () => {
        window.clearTimeout(timer);
        modal.classList.add('is-loading');
        loading.hidden = false;
        loading.setAttribute('aria-hidden', 'false');
        results.setAttribute('aria-busy', 'true');
        timer = window.setTimeout(() => {
            applyDataset();
            loading.hidden = true;
            loading.setAttribute('aria-hidden', 'true');
            results.setAttribute('aria-busy', 'false');
            modal.classList.remove('is-loading');
        }, 720);
    };

    modal.addEventListener('show.bs.modal', aggregate);
    period.addEventListener('change', aggregate);
})();

document.addEventListener('DOMContentLoaded', () => {
    const themeButton = document.getElementById('themeToggle');
    if (themeButton) {
        themeButton.addEventListener('click', () => {
            const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            document.cookie = `theme=${next}; path=/; max-age=31536000; samesite=lax`;
        });
    }

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!confirm(element.dataset.confirm || 'Are you sure?')) {
                event.preventDefault();
            }
        });
    });

    if (document.body.dataset.refresh) {
        window.setTimeout(() => window.location.reload(), Number(document.body.dataset.refresh) * 1000);
    }
});

function renderLineChart(id, labels, datasets) {
    const element = document.getElementById(id);
    if (!element || typeof Chart === 'undefined') return;

    new Chart(element, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(125,125,125,.15)' } },
                x: { grid: { display: false } }
            }
        }
    });
}

function renderBarChart(id, labels, datasets) {
    const element = document.getElementById(id);
    if (!element || typeof Chart === 'undefined') return;

    new Chart(element, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(125,125,125,.15)' } },
                x: { grid: { display: false } }
            }
        }
    });
}

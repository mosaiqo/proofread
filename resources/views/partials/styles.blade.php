:root {
    --bg: #0b0d11;
    --bg-elevated: #12151b;
    --bg-nav: #0f1217;
    --surface: #171b22;
    --surface-hover: #1d2230;
    --border: #242932;
    --border-strong: #2f3644;
    --text: #e6eaf1;
    --text-muted: #8a93a4;
    --text-dim: #5c6475;
    --accent: #7cc4ff;
    --accent-strong: #4aa1ff;
    --success: #5bd481;
    --success-bg: rgba(91, 212, 129, 0.12);
    --danger: #f0687a;
    --danger-bg: rgba(240, 104, 122, 0.12);
    --warning: #f0c86a;
    --warning-bg: rgba(240, 200, 106, 0.12);
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    --radius: 8px;
    --radius-sm: 6px;
}

* {
    box-sizing: border-box;
}

html.proofread,
html.proofread body {
    margin: 0;
    padding: 0;
    background: var(--bg);
    color: var(--text);
    font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI",
        Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

html.proofread a {
    color: var(--accent);
    text-decoration: none;
}

html.proofread a:hover {
    color: var(--accent-strong);
}

.proofread-nav {
    height: 56px;
    background: var(--bg-nav);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 20;
}

.proofread-nav-inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 32px;
}

.proofread-logo {
    font-weight: 600;
    color: var(--text);
    letter-spacing: -0.01em;
    font-size: 15px;
}

.proofread-nav-links {
    display: flex;
    gap: 20px;
    align-items: center;
}

.proofread-nav-links a {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 500;
    padding: 6px 0;
    border-bottom: 2px solid transparent;
    transition: color 0.12s ease, border-color 0.12s ease;
}

.proofread-nav-links a.active,
.proofread-nav-links a:hover {
    color: var(--text);
    border-bottom-color: var(--accent);
}

.proofread-main {
    max-width: 1280px;
    margin: 0 auto;
    padding: 24px;
}

h1.page-title {
    font-size: 22px;
    font-weight: 600;
    margin: 0 0 4px 0;
    letter-spacing: -0.01em;
}

p.page-subtitle {
    color: var(--text-muted);
    margin: 0 0 24px 0;
    font-size: 13px;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stat-card-title {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.stat-card-value {
    font-size: 26px;
    font-weight: 600;
    color: var(--text);
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
}

.stat-card-value.pass {
    color: var(--success);
}

.stat-card-value.fail {
    color: var(--danger);
}

.stat-card-subtitle {
    font-size: 12px;
    color: var(--text-dim);
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 12px;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 160px;
}

.filter-field.grow {
    flex: 1 1 240px;
}

.filter-field label {
    font-size: 11px;
    font-weight: 500;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.filter-field input,
.filter-field select {
    background: var(--bg-elevated);
    border: 1px solid var(--border-strong);
    color: var(--text);
    padding: 7px 10px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-family: inherit;
    outline: none;
    transition: border-color 0.12s ease;
}

.filter-field input:focus,
.filter-field select:focus {
    border-color: var(--accent);
}

.table-wrapper {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}

table.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

table.table thead th {
    background: var(--bg-elevated);
    color: var(--text-muted);
    font-weight: 500;
    text-align: left;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    position: sticky;
    top: 0;
}

table.table tbody td {
    padding: 11px 14px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    font-variant-numeric: tabular-nums;
}

table.table tbody tr:last-child td {
    border-bottom: none;
}

table.table tbody tr:hover {
    background: var(--surface-hover);
    cursor: pointer;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.badge.badge-pass {
    background: var(--success-bg);
    color: var(--success);
}

.badge.badge-fail {
    background: var(--danger-bg);
    color: var(--danger);
}

.badge.badge-warning {
    background: var(--warning-bg);
    color: var(--warning);
}

.muted {
    color: var(--text-muted);
}

.dim {
    color: var(--text-dim);
}

.empty-state {
    padding: 48px 24px;
    text-align: center;
    color: var(--text-muted);
}

.empty-state h3 {
    color: var(--text);
    font-size: 15px;
    margin: 0 0 4px 0;
    font-weight: 600;
}

.empty-state p {
    margin: 0;
    font-size: 13px;
}

.pagination-wrap {
    padding: 12px 14px;
    border-top: 1px solid var(--border);
    background: var(--bg-elevated);
}

.pagination-wrap nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.pagination-wrap a,
.pagination-wrap span {
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    color: var(--text-muted);
    border: 1px solid var(--border);
    background: var(--surface);
}

.pagination-wrap a:hover {
    color: var(--text);
    border-color: var(--accent);
}

.pagination-wrap span.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .proofread-main {
        padding: 16px;
    }

    .proofread-nav-inner {
        padding: 0 16px;
        gap: 16px;
    }

    .stat-grid {
        grid-template-columns: 1fr;
    }

    .filters {
        flex-direction: column;
    }

    .filter-field {
        min-width: 100%;
    }

    table.table thead {
        display: none;
    }

    table.table tbody tr {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px;
        padding: 10px 12px;
    }

    table.table tbody td {
        border: none;
        padding: 2px 0;
    }
}

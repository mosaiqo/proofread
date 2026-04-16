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

[x-cloak] {
    display: none !important;
}

.dataset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 12px;
}

.dataset-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 16px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: inherit;
    text-decoration: none;
    cursor: pointer;
    transition: border-color 0.12s ease, background 0.12s ease;
}

.dataset-card:hover {
    border-color: var(--accent);
    background: var(--surface-hover);
    color: inherit;
}

.dataset-card-head {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.dataset-name {
    font-weight: 600;
    color: var(--text);
    letter-spacing: -0.01em;
}

.dataset-card-sub {
    font-size: 12px;
}

.sparkline-wrap {
    display: flex;
    align-items: center;
    min-height: 28px;
    color: var(--text-muted);
}

.sparkline-wrap.sparkline-pass {
    color: var(--success);
}

.sparkline-wrap.sparkline-fail {
    color: var(--danger);
}

.sparkline-wrap.sparkline-warn {
    color: var(--warning);
}

.sparkline-wrap.sparkline-neutral {
    color: var(--text-muted);
}

.sparkline {
    vertical-align: middle;
    display: inline-block;
}

.sparkline-empty {
    font-size: 13px;
}

.dataset-meta-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
    flex-wrap: wrap;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 12px;
}

.breadcrumb a {
    color: var(--text-muted);
}

.breadcrumb a:hover {
    color: var(--text);
}

.breadcrumb-sep {
    color: var(--text-dim);
}

.breadcrumb-current {
    color: var(--text);
}

.run-header {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
}

.run-header-main {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.run-header-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.run-header-meta .sep {
    color: var(--text-dim);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.summary-tile {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.summary-tile-label {
    font-size: 11px;
    font-weight: 500;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.summary-tile-value {
    font-size: 20px;
    font-weight: 600;
    color: var(--text);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.01em;
}

.summary-tile-value.pass {
    color: var(--success);
}

.summary-tile-value.fail {
    color: var(--danger);
}

.cases-toolbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 8px 0;
    margin-bottom: 8px;
}

.inline-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-muted);
    cursor: pointer;
    user-select: none;
}

.inline-toggle input[type="checkbox"] {
    accent-color: var(--accent);
}

.action-cell {
    color: var(--accent);
    font-weight: 500;
}

.drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 40;
}

.drawer {
    position: fixed;
    top: 0;
    right: 0;
    height: 100vh;
    width: 480px;
    max-width: 90vw;
    background: var(--bg-elevated);
    border-left: 1px solid var(--border);
    overflow-y: auto;
    z-index: 50;
    display: flex;
    flex-direction: column;
}

.drawer-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--bg-elevated);
    z-index: 1;
}

.drawer-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text);
    letter-spacing: -0.01em;
}

.drawer-close {
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 22px;
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}

.drawer-close:hover {
    color: var(--text);
}

.drawer-body {
    padding: 16px 20px 32px 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.drawer-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.drawer-section-title {
    margin: 0;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
}

.drawer-link {
    background: transparent;
    border: 1px solid var(--border-strong);
    color: var(--accent);
    border-radius: var(--radius-sm);
    padding: 6px 10px;
    font-size: 12px;
    cursor: pointer;
    align-self: flex-start;
    font-family: inherit;
}

.drawer-link:hover {
    border-color: var(--accent);
}

.code-block {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 10px 12px;
    margin: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
    font-size: 12px;
    line-height: 1.5;
    color: var(--text);
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 360px;
    overflow-y: auto;
}

.assertion-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.assertion-row {
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.assertion-row:last-child {
    border-bottom: none;
}

.assertion-head {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.assertion-name {
    font-weight: 500;
    color: var(--text);
}

.assertion-reason {
    font-size: 12px;
}

.assertion-meta summary {
    cursor: pointer;
    color: var(--text-muted);
    font-size: 12px;
}

.error-box {
    background: var(--danger-bg);
    border: 1px solid var(--danger);
    border-radius: var(--radius-sm);
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.error-class {
    font-weight: 600;
    color: var(--danger);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
    font-size: 12px;
}

.error-message {
    color: var(--text);
    font-size: 13px;
}

.meta-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin: 0;
}

.meta-list > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.meta-list dt {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
}

.meta-list dd {
    margin: 0;
    color: var(--text);
    font-variant-numeric: tabular-nums;
}

.run-header-compare {
    position: relative;
    margin-left: auto;
}

.compare-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: var(--bg-elevated);
    border: 1px solid var(--border-strong);
    border-radius: var(--radius);
    min-width: 280px;
    max-height: 360px;
    overflow-y: auto;
    z-index: 30;
    box-shadow: var(--shadow);
    padding: 8px;
}

.compare-dropdown-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 4px 8px;
}

.compare-dropdown ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.compare-dropdown li a {
    display: block;
    padding: 6px 8px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    color: var(--text);
}

.compare-dropdown li a:hover {
    background: var(--surface-hover);
    color: var(--text);
}

.compare-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    margin-bottom: 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}

.compare-meta-col {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    border-right: 1px solid var(--border);
}

.compare-meta-col:last-child {
    border-right: none;
}

.compare-meta-id {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
    font-size: 12px;
    color: var(--text);
    word-break: break-all;
}

.compare-picker {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.compare-picker-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}

.compare-picker-form .filter-field {
    min-width: 240px;
}

.compare-picker-form .filter-field.grow {
    flex: 1 1 260px;
}

.compare-picker-form button {
    background: var(--bg-elevated);
    border: 1px solid var(--border-strong);
    color: var(--accent);
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
}

.compare-picker-form button:hover {
    border-color: var(--accent);
}

.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 8px 0 12px 0;
}

.filter-tab {
    background: var(--surface);
    border: 1px solid var(--border-strong);
    color: var(--text-muted);
    padding: 6px 12px;
    border-radius: 999px;
    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-tab:hover {
    color: var(--text);
    border-color: var(--accent);
}

.filter-tab.active {
    color: var(--text);
    border-color: var(--accent);
    background: var(--surface-hover);
}

.filter-tab-count {
    background: var(--bg-elevated);
    color: var(--text-muted);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 11px;
    padding: 1px 7px;
    font-variant-numeric: tabular-nums;
}

.delta-value {
    font-variant-numeric: tabular-nums;
}

.delta-value.pass {
    color: var(--success);
}

.delta-value.fail {
    color: var(--danger);
}

.compare-status {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid var(--border);
    display: inline-flex;
}

.compare-status.status-regression {
    color: var(--danger);
    background: var(--danger-bg);
    border-color: var(--danger);
}

.compare-status.status-improvement {
    color: var(--success);
    background: var(--success-bg);
    border-color: var(--success);
}

.compare-status.status-stable-pass,
.compare-status.status-stable-fail {
    color: var(--text-muted);
    background: var(--bg-elevated);
}

.compare-status.status-base-only,
.compare-status.status-head-only {
    color: var(--warning);
    background: var(--warning-bg);
    border-color: var(--warning);
}

.compare-failures {
    font-size: 12px;
    line-height: 1.4;
    word-break: break-word;
}

.compare-failures.fail {
    color: var(--danger);
}

.compare-failures.pass {
    color: var(--success);
}

.compare-actions {
    display: flex;
    gap: 12px;
    margin: 16px 0;
}

.case-drawer-compare {
    width: 720px;
    max-width: 96vw;
}

.compare-outputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.compare-outputs > div {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.compare-outputs .code-block {
    max-height: 260px;
}

.section-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0 0 10px 0;
}

.chart-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    margin-bottom: 20px;
}

.overview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.overview-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    margin-bottom: 20px;
}

.overview-panel .table-wrapper {
    margin-top: 4px;
}

.overview-empty {
    padding: 18px 0;
    color: var(--text-muted);
    font-size: 13px;
}

.failing-list,
.regressions-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
}

.failing-row,
.regressions-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.failing-row:last-child,
.regressions-row:last-child {
    border-bottom: none;
}

.failing-row-main {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.fail-rate {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    color: var(--danger);
}

.trend-chart {
    display: block;
    width: 100%;
}

.trend-gridline {
    stroke: var(--border);
    stroke-width: 0.5;
}

.trend-axis-label {
    fill: var(--text-muted);
    font-size: 10px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
}

.trend-line {
    stroke: var(--accent);
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.trend-point {
    fill: var(--accent);
}

.shadow-capture-row {
    cursor: pointer;
}

.badge.shadow-status-pending {
    background: var(--bg-elevated);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.badge.shadow-status-passed {
    background: var(--success-bg);
    color: var(--success);
}

.badge.shadow-status-failed {
    background: var(--danger-bg);
    color: var(--danger);
}

.promote-panel {
    border-top: 1px solid var(--border);
    padding-top: 12px;
}

.promote-panel-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px;
    margin-top: 8px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
}

.promote-snippet {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
    font-size: 12px;
    overflow-x: auto;
    background: var(--bg-elevated);
}

.copy-button {
    align-self: flex-start;
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

    .overview-grid {
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

    .drawer {
        width: 100vw;
        max-width: 100vw;
    }

    .meta-list {
        grid-template-columns: 1fr;
    }

    .compare-meta-grid {
        grid-template-columns: 1fr;
    }

    .compare-meta-col {
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .compare-meta-col:last-child {
        border-bottom: none;
    }

    .compare-outputs {
        grid-template-columns: 1fr;
    }

    .case-drawer-compare {
        width: 100vw;
        max-width: 100vw;
    }
}

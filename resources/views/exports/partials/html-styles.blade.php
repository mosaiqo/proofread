*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    color: #1f2328;
    background: #ffffff;
    margin: 0;
    padding: 32px;
    line-height: 1.5;
    font-size: 14px;
}

.wrapper {
    max-width: 960px;
    margin: 0 auto;
}

h1 {
    font-size: 28px;
    margin: 0 0 16px 0;
    font-weight: 600;
}

h2 {
    font-size: 20px;
    margin: 32px 0 12px 0;
    font-weight: 600;
    padding-bottom: 6px;
    border-bottom: 1px solid #d0d7de;
}

h3 {
    font-size: 16px;
    margin: 20px 0 8px 0;
    font-weight: 600;
}

table {
    border-collapse: collapse;
    width: 100%;
    margin: 12px 0;
}

table th, table td {
    border: 1px solid #d0d7de;
    padding: 6px 12px;
    text-align: left;
    vertical-align: top;
}

table th {
    background: #f6f8fa;
    font-weight: 600;
}

code, pre {
    font-family: SFMono-Regular, Consolas, "Liberation Mono", monospace;
    font-size: 12px;
}

code {
    background: #f6f8fa;
    padding: 2px 6px;
    border-radius: 4px;
}

pre {
    background: #f6f8fa;
    padding: 12px;
    border-radius: 6px;
    overflow-x: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
    margin: 8px 0;
}

.badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-pass { background: #dafbe1; color: #1a7f37; }
.badge-fail { background: #ffebe9; color: #cf222e; }
.badge-warn { background: #fff8c5; color: #9a6700; }

.muted { color: #656d76; }

.summary-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.summary-list li {
    padding: 4px 0;
}

.case {
    border: 1px solid #d0d7de;
    border-radius: 6px;
    padding: 16px;
    margin: 12px 0;
    background: #ffffff;
}

.case-head {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.case-meta {
    font-size: 12px;
}

.assertion-list {
    list-style: none;
    padding: 0;
    margin: 8px 0;
}

.assertion-list li {
    padding: 4px 0;
    display: flex;
    gap: 8px;
    align-items: baseline;
    flex-wrap: wrap;
}

.assertion-name {
    font-family: SFMono-Regular, Consolas, monospace;
    font-size: 12px;
}

.error-box {
    background: #ffebe9;
    border: 1px solid #ffcecb;
    padding: 12px;
    border-radius: 6px;
    margin: 8px 0;
}

.error-class {
    font-weight: 600;
    font-family: SFMono-Regular, Consolas, monospace;
    font-size: 12px;
}

footer {
    margin-top: 40px;
    padding-top: 12px;
    border-top: 1px solid #d0d7de;
    color: #656d76;
    font-size: 12px;
    text-align: center;
}

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Setup') — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #0f1419;
            --surface: #1a2332;
            --border: #2d3a4f;
            --text: #e7ecf3;
            --muted: #8b9cb3;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --success: #22c55e;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }
        .container { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        header h1 { font-size: 1.25rem; font-weight: 600; }
        header span { color: var(--muted); font-size: 0.875rem; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card h2 { font-size: 1.125rem; margin-bottom: 0.5rem; }
        .card p.desc { color: var(--muted); font-size: 0.875rem; margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--muted); }
        input[type=text], input[type=password], textarea {
            width: 100%;
            padding: 0.65rem 0.85rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.9375rem;
            margin-bottom: 1rem;
        }
        textarea { min-height: 80px; font-family: ui-monospace, monospace; font-size: 0.8125rem; }
        .btn {
            display: inline-block;
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .btn-ghost:hover { color: var(--text); border-color: var(--muted); }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        .alert-success { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.35); color: #86efac; }
        .alert-error { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .badge-ok { background: rgba(34,197,94,.2); color: #86efac; }
        .badge-no { background: rgba(239,68,68,.2); color: #fca5a5; }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .status-item {
            padding: 0.65rem 0.85rem;
            background: var(--bg);
            border-radius: 8px;
            font-size: 0.8125rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        code, .mono {
            font-family: ui-monospace, monospace;
            font-size: 0.8125rem;
            background: var(--bg);
            padding: 0.75rem;
            border-radius: 8px;
            display: block;
            word-break: break-all;
            margin-top: 0.5rem;
            border: 1px solid var(--border);
        }
        ol.steps { margin: 0.75rem 0 1rem 1.25rem; color: var(--muted); font-size: 0.875rem; }
        ol.steps li { margin-bottom: 0.35rem; }
        .catalog-table { width: 100%; font-size: 0.8125rem; border-collapse: collapse; margin-top: 0.75rem; }
        .catalog-table th, .catalog-table td {
            text-align: left;
            padding: 0.5rem 0.65rem;
            border-bottom: 1px solid var(--border);
        }
        .catalog-table th { color: var(--muted); font-weight: 500; }
        .row-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; }
        .env-line { color: #93c5fd; }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')
    </div>
</body>
</html>

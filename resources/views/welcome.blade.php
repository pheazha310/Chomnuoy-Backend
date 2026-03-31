<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0f172a;
            --panel: rgba(30, 41, 59, 0.72);
            --border: rgba(148, 163, 184, 0.18);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #f43f5e;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(244, 63, 94, 0.08), transparent 30%),
                linear-gradient(180deg, #0b1120 0%, var(--bg) 100%);
        }

        .page {
            width: min(980px, calc(100% - 32px));
            margin: 0 auto;
            padding: 72px 0 48px;
        }

        .logo {
            text-align: center;
            font-size: 42px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: var(--accent);
            margin-bottom: 40px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 22px;
        }

        .card {
            display: block;
            padding: 28px;
            min-height: 190px;
            text-decoration: none;
            color: inherit;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.35);
            transition: transform 150ms ease, border-color 150ms ease;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: rgba(244, 63, 94, 0.5);
        }

        .card h2 {
            margin: 0 0 12px;
            font-size: 22px;
        }

        .card p {
            margin: 0;
            line-height: 1.65;
            color: var(--muted);
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-top: 34px;
            font-size: 14px;
            color: var(--muted);
        }

        @media (max-width: 640px) {
            .page {
                padding-top: 48px;
            }

            .footer {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="logo">Laravel</div>

        <section class="grid">
            <a class="card" href="https://laravel.com/docs" target="_blank" rel="noreferrer">
                <h2>Documentation</h2>
                <p>Read the official Laravel documentation for routing, Eloquent, queues, testing, deployment, and framework fundamentals.</p>
            </a>

            <a class="card" href="https://laracasts.com" target="_blank" rel="noreferrer">
                <h2>Laracasts</h2>
                <p>Follow practical video tutorials covering Laravel, PHP, JavaScript, testing, and modern full-stack workflows.</p>
            </a>

            <a class="card" href="https://laravel-news.com" target="_blank" rel="noreferrer">
                <h2>Laravel News</h2>
                <p>Track new packages, releases, deployment patterns, and community updates across the Laravel ecosystem.</p>
            </a>

            <a class="card" href="https://laravel.com" target="_blank" rel="noreferrer">
                <h2>Vibrant Ecosystem</h2>
                <p>Explore Forge, Vapor, Nova, Cashier, Horizon, Sanctum, and other first-party tools around the framework.</p>
            </a>
        </section>

        <footer class="footer">
            <span>Chomnuoy Backend deployment page</span>
            <span>Laravel {{ app()->version() }} | PHP {{ PHP_VERSION }}</span>
        </footer>
    </main>
</body>
</html>

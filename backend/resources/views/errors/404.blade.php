<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link not found · LinkForge</title>
    <style>
        :root {
            --bg: #f6f7fb;
            --text: #111827;
            --text-secondary: #667085;
            --text-muted: #98a2b3;
            --border: #e7eaf0;
            --primary: #635bff;
            --primary-soft: #efeeff;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; min-height: 100%; }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system,
                "Segoe UI", sans-serif;
            color: var(--text);
            background: var(--bg);
            overflow: hidden;
            position: relative;
            -webkit-font-smoothing: antialiased;
        }

        .glow {
            position: absolute;
            width: 760px;
            height: 760px;
            top: -320px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 50%;
            background: rgba(99, 91, 255, 0.12);
            filter: blur(110px);
            pointer-events: none;
        }

        .brand {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 26px 32px;
            font-weight: 750;
            font-size: 17px;
            letter-spacing: -0.02em;
        }

        .brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 10px;
            color: #fff;
            font-weight: 800;
            background: linear-gradient(135deg, #7167ff, #5146e5);
        }

        .container {
            position: relative;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 24px 80px;
        }

        .icon {
            display: grid;
            place-items: center;
            width: 72px;
            height: 72px;
            border-radius: 20px;
            color: var(--primary);
            background: var(--primary-soft);
            box-shadow: 0 12px 40px rgba(99, 91, 255, 0.18);
        }

        .code {
            margin-top: 26px;
            font-size: clamp(64px, 12vw, 108px);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.05em;
            background: linear-gradient(135deg, #7167ff, #5146e5);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        h1 {
            margin: 10px 0 12px;
            font-size: clamp(24px, 4vw, 32px);
            letter-spacing: -0.03em;
        }

        p {
            max-width: 460px;
            margin: 0 auto;
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
        }

        .actions { margin-top: 32px; }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 48px;
            padding: 0 22px;
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: 0.18s;
        }

        .button:hover {
            background: #5046e5;
            transform: translateY(-1px);
        }

        footer {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 32px;
            color: var(--text-muted);
            font-size: 12px;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 560px) {
            footer { flex-direction: column; gap: 6px; }
        }
    </style>
</head>
<body>
    <div class="glow"></div>

    <div class="brand">
        <span class="brand-mark">L</span>
        LinkForge
    </div>

    <div class="container">
        <div class="icon">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round">
                <path d="m13.5 8.5-5 5"></path>
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
            </svg>
        </div>

        <div class="code">404</div>

        <h1>This link doesn't exist</h1>

        <p>
            The short link you followed is invalid, expired, or has been
            removed. Double-check the address and try again.
        </p>

        <div class="actions">
            <a class="button" href="{{ config('app.frontend_url', 'http://127.0.0.1:5173') }}">
                Create a new short link
            </a>
        </div>
    </div>

    <footer>
        <span>&copy; {{ date('Y') }} LinkForge</span>
        <span>Secure URL Management</span>
    </footer>
</body>
</html>

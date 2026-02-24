<!--
  Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
  All rights reserved.

  This source code is proprietary and confidential.
  Unauthorised copying, modification, or distribution is strictly prohibited.
-->

<!DOCTYPE html>
<html lang="en-GB" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G2My.link — Coming Soon</title>
    <meta name="description" content="G2My.link — The shortlink redirect domain for Go2My.link. Coming soon.">
    <meta name="robots" content="noindex, nofollow">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Auto-refresh every 15 minutes (900 seconds) -->
    <meta http-equiv="refresh" content="900">

    <style>
        /* =================================================================
           G2My.Link — Coming Soon Landing Page
           Minimal branding — redirect domain
           ================================================================= */

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --brand-blue: #1E88E5;
            --brand-blue-dark: #1565C0;
            --brand-blue-light: #4FC3F7;
            --brand-green: #43A047;
            --brand-green-dark: #2E7D32;
            --brand-grey: #555555;
            --text-primary: #333333;
            --text-secondary: #666666;
            --bg-primary: #ffffff;
            --bg-gradient-start: #f8f9fa;
            --bg-gradient-end: #e8f4fd;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #e0e0e0;
                --text-secondary: #b0b0b0;
                --bg-primary: #1a1a2e;
                --bg-gradient-start: #16213e;
                --bg-gradient-end: #0f3460;
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--text-primary);
            background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            line-height: 1.6;
        }

        .skip-link {
            position: absolute;
            top: -100%;
            left: 0;
            background: var(--brand-blue);
            color: #ffffff;
            padding: 0.5rem 1rem;
            z-index: 1000;
            text-decoration: none;
        }

        .skip-link:focus {
            top: 0;
        }

        .container {
            max-width: 500px;
            width: 100%;
            text-align: center;
            margin-top: auto;
            margin-bottom: auto;
        }

        /* Minimal logo — icon only for redirect domain */
        .logo {
            margin-bottom: 2rem;
        }

        .logo img {
            width: 120px;
            height: auto;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--brand-blue);
        }

        .tagline {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .info {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(30, 136, 229, 0.15);
        }

        @media (prefers-color-scheme: dark) {
            .info {
                background: rgba(255, 255, 255, 0.08);
                border-color: rgba(79, 195, 247, 0.2);
            }
        }

        .info p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .info a {
            color: var(--brand-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .info a:hover {
            text-decoration: underline;
        }

        .info a:focus-visible {
            outline: 2px solid var(--brand-blue);
            outline-offset: 2px;
        }

        /* Footer — pinned to bottom */
        footer {
            margin-top: auto;
            padding-top: 2rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        footer a {
            color: var(--brand-blue);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.5rem;
            }
        }

        /* Countdown ring — bottom-right, large screens only */
        #countdown-ring {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 1000;
            opacity: 0.4;
            transition: opacity 0.3s;
        }

        #countdown-ring:hover {
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            #countdown-ring {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <a href="#main" class="skip-link">Skip to main content</a>

    <main id="main" class="container" role="main">
        <!-- Logo -->
        <div class="logo" aria-label="G2My.link logo">
            <picture>
                <source srcset="https://go2my.link/img/logo.svg" type="image/svg+xml">
                <img src="https://go2my.link/img/logo.png"
                     alt="Go2My.Link"
                     width="120"
                     height="auto"
                     loading="eager">
            </picture>
        </div>

        <h1>G2My.link</h1>
        <p class="tagline">Shortlink Redirect Domain</p>

        <div class="info">
            <p>This domain serves as the redirect engine for <a href="https://go2my.link">Go2My.link</a> shortened URLs.</p>
            <p style="margin-top: 0.75rem;">The full service is coming soon. Visit <a href="https://go2my.link">go2my.link</a> for more information.</p>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 <a href="https://www.MWBMpartners.LTD">MWBM Partners Ltd</a> (MWservices)</p>
    </footer>

<!-- Countdown ring (large screens only) -->
<div id="countdown-ring" aria-hidden="true">
    <svg width="28" height="28" viewBox="0 0 28 28">
        <circle cx="14" cy="14" r="12" fill="none" stroke="rgba(0,0,0,0.08)" stroke-width="2.5"/>
        <circle id="countdown-progress" cx="14" cy="14" r="12" fill="none" stroke="var(--brand-blue)" stroke-width="2.5"
                stroke-dasharray="75.398" stroke-dashoffset="75.398"
                stroke-linecap="round" transform="rotate(-90 14 14)"/>
    </svg>
</div>
<script>
    (function () {
        var meta = document.querySelector('meta[http-equiv="refresh"]');
        var duration = meta ? parseInt(meta.getAttribute('content'), 10) || 900 : 900;
        var circle = document.getElementById('countdown-progress');
        if (!circle) return;
        var circumference = 75.398;
        var start = Date.now();
        function tick() {
            var progress = Math.min((Date.now() - start) / (duration * 1000), 1);
            circle.style.strokeDashoffset = circumference * (1 - progress);
            if (progress >= 1) { window.location.reload(); }
            else { requestAnimationFrame(tick); }
        }
        requestAnimationFrame(tick);
    })();
</script>
</body>
</html>

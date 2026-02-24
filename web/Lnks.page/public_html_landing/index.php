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
    <title>Lnks.page — Coming Soon</title>
    <meta name="description" content="Lnks.page — Create beautiful, customisable link listing pages. A LinksPage service by MWservices. Coming soon.">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title" content="Lnks.page — Coming Soon">
    <meta property="og:description" content="Create beautiful, customisable link listing pages. Your links, your way.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://lnks.page">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Auto-refresh every 15 minutes (900 seconds) -->
    <meta http-equiv="refresh" content="900">

    <style>
        /* =================================================================
           Lnks.page — Coming Soon Landing Page
           Sub-brand of Go2My.link, LinksPage service
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
            --brand-green-light: #66BB6A;
            --brand-grey: #555555;
            --text-primary: #333333;
            --text-secondary: #666666;
            --bg-primary: #ffffff;
            --bg-gradient-start: #f0faf0;
            --bg-gradient-end: #e8f5e9;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #e0e0e0;
                --text-secondary: #b0b0b0;
                --bg-primary: #1a2e1a;
                --bg-gradient-start: #162e16;
                --bg-gradient-end: #0f3e0f;
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
            background: var(--brand-green);
            color: #ffffff;
            padding: 0.5rem 1rem;
            z-index: 1000;
            text-decoration: none;
        }

        .skip-link:focus {
            top: 0;
        }

        .container {
            max-width: 560px;
            width: 100%;
            text-align: center;
        }

        /* Logo area */
        .logo {
            margin-bottom: 1.5rem;
        }

        .logo img {
            width: 150px;
            height: auto;
            margin-bottom: 0.5rem;
        }

        .logo-text {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .logo-text .lnks {
            color: var(--brand-green);
        }

        .logo-text .page {
            color: var(--brand-grey);
        }

        .powered-by {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .powered-by a {
            color: var(--brand-blue);
            text-decoration: none;
        }

        .powered-by a:hover {
            text-decoration: underline;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--brand-green) 0%, var(--brand-green-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .tagline {
            font-size: 1.15rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        /* Features */
        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
            margin-bottom: 2.5rem;
        }

        .feature {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            padding: 0.65rem 1.1rem;
            font-size: 0.85rem;
            color: var(--text-primary);
            border: 1px solid rgba(67, 160, 71, 0.15);
        }

        @media (prefers-color-scheme: dark) {
            .feature {
                background: rgba(255, 255, 255, 0.08);
                border-color: rgba(129, 199, 132, 0.2);
            }
        }

        /* Email form */
        .notify-section {
            margin-bottom: 2rem;
        }

        .notify-section p {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .email-form {
            display: flex;
            gap: 0.5rem;
            max-width: 400px;
            margin: 0 auto;
        }

        .email-form input[type="email"] {
            flex: 1;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .email-form input[type="email"]:focus {
            border-color: var(--brand-green);
            box-shadow: 0 0 0 3px rgba(67, 160, 71, 0.15);
        }

        .email-form button {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, var(--brand-green) 0%, var(--brand-green-dark) 100%);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            font-family: inherit;
            white-space: nowrap;
        }

        .email-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(67, 160, 71, 0.3);
        }

        .email-form button:focus-visible {
            outline: 3px solid var(--brand-green-light);
            outline-offset: 2px;
        }

        .email-form button:active {
            transform: translateY(0);
        }

        footer {
            margin-top: 3rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        footer a {
            color: var(--brand-green);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        footer a:focus-visible {
            outline: 2px solid var(--brand-green);
            outline-offset: 2px;
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.5rem;
            }

            .logo-text {
                font-size: 2rem;
            }

            .email-form {
                flex-direction: column;
            }

            .email-form button {
                width: 100%;
            }
        }

        /* Visually hidden — accessible label for screen readers */
        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
            border: 0;
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
        <div class="logo" aria-label="Lnks.page logo">
            <picture>
                <source srcset="https://go2my.link/img/logo.svg" type="image/svg+xml">
                <img src="https://go2my.link/img/logo.png"
                     alt="Go2My.Link"
                     width="150"
                     height="auto"
                     loading="eager">
            </picture>
            <div class="logo-text">
                <span class="lnks">Lnks</span><span class="page">.page</span>
            </div>
            <div class="powered-by">by <a href="https://go2my.link">Go2My.link</a></div>
        </div>

        <!-- Heading -->
        <h1>Coming Soon</h1>
        <p class="tagline">Create beautiful, customisable link listing pages</p>

        <!-- Features -->
        <div class="features" aria-label="Upcoming features">
            <span class="feature">Customisable Templates</span>
            <span class="feature">Custom Domains</span>
            <span class="feature">WYSIWYG Editor</span>
            <span class="feature">Click Tracking</span>
            <span class="feature">Auto Favicons</span>
        </div>

        <!-- Email capture -->
        <section class="notify-section" aria-label="Email notification signup">
            <p>Get notified when we launch.</p>
            <form class="email-form" action="#" method="post" aria-label="Notification signup form">
                <label for="email" class="visually-hidden">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                    aria-label="Your email address"
                >
                <button type="submit">Notify Me</button>
            </form>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 <a href="https://www.MWBMpartners.LTD">MWBM Partners Ltd</a> (MWservices)</p>
    </footer>

<!-- Countdown ring (large screens only) -->
<div id="countdown-ring" aria-hidden="true">
    <svg width="28" height="28" viewBox="0 0 28 28">
        <circle cx="14" cy="14" r="12" fill="none" stroke="rgba(0,0,0,0.08)" stroke-width="2.5"/>
        <circle id="countdown-progress" cx="14" cy="14" r="12" fill="none" stroke="var(--brand-green)" stroke-width="2.5"
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

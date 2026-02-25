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
    <title>Go2My.link — Coming Soon</title>
    <meta name="description" content="Go2My.link — A powerful URL shortening service by MWservices. Coming soon.">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title" content="Go2My.link — Coming Soon">
    <meta property="og:description" content="A powerful URL shortening service. Shorten, track, and manage your links.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://go2my.link">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Auto-refresh every 15 minutes (900 seconds) -->
    <meta http-equiv="refresh" content="900">

    <style>
        /* =================================================================
           Go2My.Link — Coming Soon Landing Page Styles
           Brand colours: Blue #1E88E5, Green #43A047, Grey #555555
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
            --brand-grey-light: #5A5F6A;
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

        html {
            font-size: 16px;
            scroll-behavior: smooth;
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

        /* Skip to content link for accessibility */
        .skip-link {
            position: absolute;
            top: -100%;
            left: 0;
            background: var(--brand-blue);
            color: #ffffff;
            padding: 0.5rem 1rem;
            z-index: 1000;
            font-size: 0.875rem;
            text-decoration: none;
            border-radius: 0 0 4px 0;
        }

        .skip-link:focus {
            top: 0;
        }

        .container {
            max-width: 600px;
            width: 100%;
            text-align: center;
            margin-top: auto;
            margin-bottom: auto;
        }

        /* Logo */
        .logo {
            margin-bottom: 2rem;
        }

        .logo img {
            max-width: 300px;
            width: 100%;
            height: auto;
        }

        /* Heading */
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--brand-blue) 0%, var(--brand-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .tagline {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        /* Features */
        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2.5rem;
        }

        .feature {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            font-size: 0.9rem;
            color: var(--text-primary);
            border: 1px solid rgba(30, 136, 229, 0.15);
            flex: 0 1 auto;
        }

        @media (prefers-color-scheme: dark) {
            .feature {
                background: rgba(255, 255, 255, 0.08);
                border-color: rgba(79, 195, 247, 0.2);
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
            max-width: 420px;
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
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.15);
        }

        .email-form button {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, var(--brand-blue) 0%, var(--brand-blue-dark) 100%);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            font-family: inherit;
            white-space: nowrap;
        }

        .email-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 136, 229, 0.3);
        }

        .email-form button:focus-visible {
            outline: 3px solid var(--brand-blue-light);
            outline-offset: 2px;
        }

        .email-form button:active {
            transform: translateY(0);
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

        footer a:focus-visible {
            outline: 2px solid var(--brand-blue);
            outline-offset: 2px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            h1 {
                font-size: 1.8rem;
            }

            .tagline {
                font-size: 1rem;
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

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>
<body>
    <a href="#main" class="skip-link">Skip to main content</a>

    <main id="main" class="container" role="main">
        <!-- Logo -->
        <div class="logo" aria-label="Go2My.link logo">
            <picture>
                <source srcset="https://go2my.link/img/logo.svg" type="image/svg+xml">
                <img src="https://go2my.link/img/logo.png"
                     alt="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>"
                     height="150"
                     width="auto"
                     class="mb-2"
                     loading="lazy">
            </picture>
        </div>

        <!-- Heading -->
        <h1>Coming Soon</h1>
        <p class="tagline">A powerful URL shortening platform by MWservices</p>

        <!-- Features preview -->
        <?php
            echo "<div class=\"features\" aria-label=\"Upcoming features\">";
            echo "<span class=\"feature\">URL Shortening</span>";
            echo "<span class=\"feature\">Custom Domains</span>";
            echo "<span class=\"feature\">Click Analytics</span>";
            echo "</div>";
        ?>

        <!-- Email capture -->
        <!--<section class="notify-section" aria-label="Email notification signup">
            <p>Be the first to know when we launch.</p>
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
        </section>-->
    </main>

    <footer>
        <p>&copy; 2026 <a href="https://www.MWBMPartners.ltd">MWBM Partners Ltd</a> (t/a MWservices). All rights reserved.</p>
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

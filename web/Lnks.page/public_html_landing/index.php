<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */
?>
<!DOCTYPE html>
<html lang="en-GB" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lnks.Page — one page for all your links</title>
    <meta name="description" content="Make one page that lists all your links, in a ready-made design. Included with a free Go2My.Link account.">
    <meta name="robots" content="index, follow">
    <meta name="color-scheme" content="light dark">
    <link rel="canonical" href="https://lnks.page/">

    <?php // Open Graph ?>
    <meta property="og:title" content="Lnks.Page — one page for all your links">
    <meta property="og:description" content="Make one page that lists all your links, in a ready-made design. Included with a free Go2My.Link account.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://lnks.page/">
    <meta property="og:site_name" content="Lnks.Page">
    <meta property="og:image" content="https://go2my.link/img/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Go2My.Link">
    <meta name="twitter:card" content="summary_large_image">

    <?php // Favicon ?>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="512x512" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">

    <style>
        /* =================================================================
           Lnks.Page — Holding Page
           Sub-brand of Go2My.Link, LinksPage service
           ================================================================= */

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* Every text colour below is at least 4.5:1 against its background. */
        :root {
            --brand-green: #43A047;
            --text-primary: #333333;
            --text-secondary: #555555;
            --link: #1B5E20;
            --skip-background: #1B5E20;
            --bg-gradient-start: #f0faf0;
            --bg-gradient-end: #e8f5e9;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #e0e0e0;
                --text-secondary: #c4c4c4;
                --link: #A5D6A7;
                --bg-gradient-start: #162e16;
                --bg-gradient-end: #0f2e0f;
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
            overflow-wrap: anywhere;
        }

        .skip-link {
            position: absolute;
            top: -100%;
            left: 0;
            background: var(--skip-background);
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
            margin-top: auto;
            margin-bottom: auto;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--link);
        }

        h2 {
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }

        .tagline {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .message {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Features */
        .features {
            color: var(--text-secondary);
            text-align: left;
            padding-left: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .features li {
            margin-bottom: 0.5rem;
        }

        .message a {
            color: var(--link);
        }

        /* Footer — pinned to bottom */
        footer {
            margin-top: auto;
            padding-top: 2rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        footer a {
            color: var(--link);
        }

        a:focus-visible {
            outline: 3px solid var(--link);
            outline-offset: 3px;
        }

        @media (max-width: 480px) {
            body {
                padding: 1.25rem;
            }

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
        <?php
        // This page is served when the domain points at this folder. It has no
        // application behind it, so its words are plain English here, and the
        // logo (which lives in public_html) is not used. Only list what the
        // product really does (#320, D-012).
        ?>
        <h1>Lnks.Page</h1>
        <p class="tagline">One page for all your links</p>
        <p class="message">Lnks.Page gives you one web address that lists your links. Share it in a social media profile, an email signature or on printed material.</p>

        <h2>What you can do</h2>
        <ul class="features">
            <li>Choose your own ending for the address, such as lnks.page/yourname, if nobody else has taken it.</li>
            <li>Add your Go2My.Link short links or any other web address, and change their order or hide them later.</li>
            <li>Pick one of five ready-made designs and the font you like.</li>
            <li>Add a title, a short description, a picture that is already online, and links to your social media profiles.</li>
            <li>Preview your page, and publish or unpublish it whenever you like.</li>
            <li>Mark a link as adult content, and visitors must confirm they are 18 or older before they see the page.</li>
        </ul>

        <p class="message">Lnks.Page is part of Go2My.Link and is included with a free account. <a href="https://go2my.link/">Visit Go2My.Link</a></p>

        <?php // A sign-up form used to be here. It posted to "#" and every address typed in was lost (#211). Add it back only with a real handler that stores the address with consent wording, CSRF protection and a rate limit. ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <a href="https://www.MWBMpartners.LTD">MWBM Partners Ltd</a> (MWservices)</p>
    </footer>

<?php // Countdown ring (large screens only) ?>
<div id="countdown-ring" aria-hidden="true">
    <svg width="28" height="28" viewBox="0 0 28 28">
        <circle cx="14" cy="14" r="12" fill="none" stroke="rgba(0,0,0,0.08)" stroke-width="2.5"/>
        <circle id="countdown-progress" cx="14" cy="14" r="12" fill="none" stroke="var(--brand-green)" stroke-width="2.5"
                stroke-dasharray="75.398" stroke-dashoffset="75.398"
                stroke-linecap="round" transform="rotate(-90 14 14)"/>
    </svg>
</div>
<?php
// The ring shows 15 minutes (900 seconds) passing, and then the page reloads.
//
// Reduced motion (#104, #105): a visitor who has asked for less motion gets no
// ring animation and no automatic reload. A reload that cannot be paused or
// stopped would fail WCAG 2.2.1 (the timing rule), so the script checks the
// media query once, hides the decorative ring and returns before it schedules
// any animation frame or reload.
//
// Everyone else keeps the ring and the reload. When the ring is full the page
// reloads only if the tab is visible and no form control has focus. Otherwise
// the cycle quietly starts again, so nobody is interrupted part-way through
// typing, and the timing is still not a trap.
?>
<script>
    (function () {
        var AUTO_RELOAD_SECONDS = 900;
        var CIRCUMFERENCE = 75.398;

        var ring = document.getElementById('countdown-ring');
        var circle = document.getElementById('countdown-progress');
        if (!circle) {
            return;
        }

        var reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (reducedMotionQuery.matches) {
            if (ring) {
                ring.style.display = 'none';
            }
            return;
        }

        var start = Date.now();

        function aFormControlHasFocus() {
            var active = document.activeElement;
            if (!active) {
                return false;
            }
            var tag = active.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'BUTTON') {
                return true;
            }
            if (active.isContentEditable === true) {
                return true;
            }
            return false;
        }

        function tick() {
            var elapsed = Date.now() - start;
            var progress = Math.min(elapsed / (AUTO_RELOAD_SECONDS * 1000), 1);
            circle.style.strokeDashoffset = CIRCUMFERENCE * (1 - progress);

            if (progress >= 1) {
                if (document.hidden === true || aFormControlHasFocus() === true) {
                    start = Date.now();
                    requestAnimationFrame(tick);
                } else {
                    window.location.reload();
                }
            } else {
                requestAnimationFrame(tick);
            }
        }

        requestAnimationFrame(tick);
    })();
</script>
</body>
</html>

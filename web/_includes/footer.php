<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 📄 Go2My.Link — HTML Footer Include
 * ============================================================================
 *
 * Closes the <main> element, outputs the footer with links, includes
 * JavaScript files (Bootstrap and the site's own), and the debug panel (when
 * G2ML_DEBUG is true). The cookie consent banner is printed by header.php.
 *
 * Dependencies: page_init.php (must be loaded first)
 *
 * @package    Go2My.Link
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 2 (theme support Phase 3, cookie consent Phase 6)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names. Comparing names
// wrongly fired when a different file with the same name (for example the
// admin cron.php endpoint loading the shared cron.php library) was the script
// being run, which redirected before the endpoint's jobs could run (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

$currentYear = date('Y');

// The Quick Links and Legal links below point at pages that exist only on
// the main site, so they go through g2ml_mainSiteURL() (page_init.php).
// On the dashboard a plain "/about" would give "page not found".
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
?>

</main>

<footer class="bg-dark text-light py-4 mt-auto" data-bs-theme="dark" role="contentinfo">
    <div class="container">
        <div class="row">

            <?php // Column 1: brand and copyright. ?>
            <div class="col-md-4 mb-3">
                <?php
                // The logo's dark lettering would nearly disappear on the dark footer, so it sits on a white rounded chip.
                // The image is 160 pixels wide (320 for sharp screens), the same two files the navigation bar loads, so
                // they are fetched once. The old logo was a 350 KB picture wrapped as an SVG (#285).
                ?>
                <span class="d-inline-block bg-white rounded px-2 py-1 mb-2">
                    <img src="https://go2my.link/img/logo-160.png"
                         srcset="https://go2my.link/img/logo-160.png 1x, https://go2my.link/img/logo-320.png 2x"
                         alt="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>"
                         height="32"
                         width="142"
                         class="d-block"
                         loading="lazy">
                </span>
                <p class="text-body-secondary small">
                    <?php if (function_exists('getSetting')) { echo htmlspecialchars(getSetting('site.tagline', 'Shorten. Track. Manage.'), ENT_QUOTES, 'UTF-8'); } else { echo 'Shorten. Track. Manage.'; } ?>
                </p>
                <p class="text-body-secondary small mb-0">
                    &copy; <?php echo $currentYear; ?> MWBM Partners Ltd.
                    <?php if (function_exists('__')) { echo __('footer.rights'); } else { echo 'All rights reserved.'; } ?>
                </p>
            </div>

            <?php // Column 2: quick links. ?>
            <div class="col-md-4 mb-3">
                <h2 class="h6"><?php if (function_exists('__')) { echo __('footer.quick_links'); } else { echo 'Quick Links'; } ?></h2>
                <ul class="list-unstyled small">
                    <li><a href="<?php echo g2ml_mainSiteURL('/about'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('nav.about'); } else { echo 'About'; } ?></a></li>
                    <li><a href="<?php echo g2ml_mainSiteURL('/features'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('nav.features'); } else { echo 'Features'; } ?></a></li>
                    <li><a href="<?php echo g2ml_mainSiteURL('/pricing'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('nav.pricing'); } else { echo 'Pricing'; } ?></a></li>
                    <li><a href="<?php echo g2ml_mainSiteURL('/help'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.help'); } else { echo 'Help &amp; Guides'; } ?></a></li>
                    <li><a href="<?php echo g2ml_mainSiteURL('/contact'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.contact'); } else { echo 'Contact'; } ?></a></li>
                </ul>
            </div>

            <?php // Column 3: legal links. ?>
            <div class="col-md-4 mb-3">
                <h2 class="h6"><?php if (function_exists('__')) { echo __('footer.legal'); } else { echo 'Legal'; } ?></h2>
                <ul class="list-unstyled small">
                    <li><a href="<?php echo g2ml_mainSiteURL('/legal/terms'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.terms'); } else { echo 'Terms of Use'; } ?></a></li>
                    <li><a href="<?php echo g2ml_mainSiteURL('/legal/privacy'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.privacy'); } else { echo 'Privacy Policy'; } ?></a></li>
                    <li><a href="<?php echo g2ml_mainSiteURL('/legal/cookies'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.cookies'); } else { echo 'Cookie Policy'; } ?></a></li>
                    <li><a href="<?php echo g2ml_mainSiteURL('/legal/acceptable-use'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.aup'); } else { echo 'Acceptable Use'; } ?></a></li>
                    <li><a href="<?php echo g2ml_mainSiteURL('/legal/copyright'); ?>" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.copyright'); } else { echo 'Copyright'; } ?></a></li>
                </ul>
            </div>

        </div>
    </div>
</footer>

<?php
// The cookie consent banner is printed by header.php, straight after the skip
// link, so keyboard users reach it first (#287).
//
// The live region below is where script announces changes to screen readers.
// The translation widget that follows is the interim machine translation.
?>
<?php if (function_exists('ariaLiveRegion')) { echo ariaLiveRegion('global-status', '', 'polite', 'status'); } ?>

<?php
if (file_exists(G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'translate_widget.php'))
{
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'translate_widget.php';
}

// Bootstrap 5.3 JavaScript bundle (it includes Popper): from the CDN first.
// If the CDN script did not load, "bootstrap" is not defined, and the second
// script writes a tag for the copy in this site's own /vendor/ folder. It must
// be written with document.write so the copy runs before the scripts below
// that need it. jQuery used to be loaded here as well, but nothing used it, so
// it was removed (#285).
// Reference: https://getbootstrap.com/docs/5.3/getting-started/
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script>
    if (typeof bootstrap === 'undefined') {
        document.write('<script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"><\/script>');
    }
</script>

<script src="/js/app.js" defer></script>
<?php // forms.js (#286): the "Working…" label and double-submit guard. The label travels in the data attribute, so no inline script is needed. ?>
<script src="/js/forms.js"
        data-g2ml-working-label="<?php if (function_exists('__')) { echo htmlspecialchars(__('common.working'), ENT_QUOTES, 'UTF-8'); } else { echo 'Working…'; } ?>"
        defer></script>
<script src="/js/cookie-consent.js" defer></script>

<?php
// ========================================================================
// 🐛 Debug Panel (only shown when G2ML_DEBUG is true)
// ========================================================================
if (defined('G2ML_DEBUG') && G2ML_DEBUG === true && function_exists('g2ml_getDebugInfo'))
{
    $debug = g2ml_getDebugInfo();
    ?>
    <div id="g2ml-debug-panel"
         style="position:fixed;bottom:0;left:0;right:0;max-height:40vh;overflow-y:auto;
                background:#1a1a2e;color:#e0e0e0;font-family:monospace;font-size:12px;
                padding:10px 15px;z-index:99999;border-top:2px solid #e94560;">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <strong style="color:#e94560;">🐛 Debug Panel</strong>
            <span>
                <?php echo htmlspecialchars($debug['environment'], ENT_QUOTES, 'UTF-8'); ?> |
                <?php echo htmlspecialchars($debug['component'], ENT_QUOTES, 'UTF-8'); ?> |
                PHP <?php echo htmlspecialchars($debug['phpVersion'], ENT_QUOTES, 'UTF-8'); ?> |
                <?php echo htmlspecialchars($debug['executionTime'], ENT_QUOTES, 'UTF-8'); ?> |
                <?php echo htmlspecialchars($debug['peakMemory'], ENT_QUOTES, 'UTF-8'); ?> |
                <?php echo $debug['queryCount']; ?> queries |
                <?php echo htmlspecialchars($debug['locale'], ENT_QUOTES, 'UTF-8'); ?>
                (<?php echo htmlspecialchars($debug['textDirection'], ENT_QUOTES, 'UTF-8'); ?>)
            </span>
            <button onclick="this.parentElement.parentElement.style.display='none'"
                    style="background:none;border:none;color:#e94560;cursor:pointer;font-size:16px;"
                    aria-label="Close debug panel">&times;</button>
        </div>

        <?php if (!empty($debug['queries'])) { ?>
        <details>
            <summary style="cursor:pointer;color:#0abde3;">Queries (<?php echo $debug['queryCount']; ?>)</summary>
            <table style="width:100%;border-collapse:collapse;margin-top:5px;">
                <thead>
                    <tr style="color:#feca57;">
                        <th scope="col" style="text-align:left;padding:2px 5px;">#</th>
                        <th scope="col" style="text-align:left;padding:2px 5px;">SQL</th>
                        <th scope="col" style="text-align:right;padding:2px 5px;">Time</th>
                        <th scope="col" style="text-align:center;padding:2px 5px;">OK</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($debug['queries'] as $i => $q) { ?>
                    <tr style="border-top:1px solid #333;">
                        <td style="padding:2px 5px;color:#999;"><?php echo $i + 1; ?></td>
                        <td style="padding:2px 5px;max-width:600px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="<?php echo htmlspecialchars($q['sql'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(substr($q['sql'], 0, 120), ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td style="padding:2px 5px;text-align:right;"><?php echo $q['duration']; ?>ms</td>
                        <td style="padding:2px 5px;text-align:center;"><?php if ($q['success']) { echo '✅'; } else { echo '❌'; } ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </details>
        <?php } ?>
    </div>
    <?php
}
?>

</body>
</html>

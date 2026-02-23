<?php
/**
 * ============================================================================
 * 📄 Go2My.Link — HTML Footer Include
 * ============================================================================
 *
 * Closes the <main> element, outputs the footer with links, includes
 * JavaScript files (Bootstrap, jQuery, custom), cookie consent placeholder,
 * and debug panel (when G2ML_DEBUG is true).
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
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

$currentYear = date('Y');
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
?>

</main><!-- /#main-content -->

<!-- ====================================================================== -->
<!-- Footer                                                                 -->
<!-- ====================================================================== -->
<footer class="bg-dark text-light py-4 mt-auto" data-bs-theme="dark" role="contentinfo">
    <div class="container">
        <div class="row">

            <!-- Column 1: Brand & Copyright -->
            <div class="col-md-4 mb-3">
                <h5><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></h5>
                <p class="text-body-secondary small">
                    <?php if (function_exists('getSetting')) { echo htmlspecialchars(getSetting('site.tagline', 'Shorten. Track. Manage.'), ENT_QUOTES, 'UTF-8'); } else { echo 'Shorten. Track. Manage.'; } ?>
                </p>
                <p class="text-body-secondary small mb-0">
                    &copy; <?php echo $currentYear; ?> MWBM Partners Ltd.
                    <?php if (function_exists('__')) { echo __('footer.rights'); } else { echo 'All rights reserved.'; } ?>
                </p>
            </div>

            <!-- Column 2: Quick Links -->
            <div class="col-md-4 mb-3">
                <h6><?php if (function_exists('__')) { echo __('footer.quick_links'); } else { echo 'Quick Links'; } ?></h6>
                <ul class="list-unstyled small">
                    <li><a href="/about" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('nav.about'); } else { echo 'About'; } ?></a></li>
                    <li><a href="/features" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('nav.features'); } else { echo 'Features'; } ?></a></li>
                    <li><a href="/pricing" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('nav.pricing'); } else { echo 'Pricing'; } ?></a></li>
                    <li><a href="/contact" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.contact'); } else { echo 'Contact'; } ?></a></li>
                </ul>
            </div>

            <!-- Column 3: Legal -->
            <div class="col-md-4 mb-3">
                <h6><?php if (function_exists('__')) { echo __('footer.legal'); } else { echo 'Legal'; } ?></h6>
                <ul class="list-unstyled small">
                    <li><a href="/legal/terms" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.terms'); } else { echo 'Terms of Use'; } ?></a></li>
                    <li><a href="/legal/privacy" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.privacy'); } else { echo 'Privacy Policy'; } ?></a></li>
                    <li><a href="/legal/cookies" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.cookies'); } else { echo 'Cookie Policy'; } ?></a></li>
                    <li><a href="/legal/acceptable-use" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.aup'); } else { echo 'Acceptable Use'; } ?></a></li>
                    <li><a href="/legal/copyright" class="text-body-secondary text-decoration-none"><?php if (function_exists('__')) { echo __('footer.copyright'); } else { echo 'Copyright'; } ?></a></li>
                </ul>
            </div>

        </div>
    </div>
</footer>

<!-- ====================================================================== -->
<!-- 🍪 Cookie Consent Banner                                               -->
<!-- ====================================================================== -->
<?php
if (file_exists(G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'cookie_banner.php'))
{
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'cookie_banner.php';
}
?>

<!-- ====================================================================== -->
<!-- ARIA Live Region for Dynamic Status Updates                            -->
<!-- ====================================================================== -->
<?php if (function_exists('ariaLiveRegion')) { echo ariaLiveRegion('global-status', '', 'polite', 'status'); } ?>

<!-- ====================================================================== -->
<!-- Interim Translation Widget                                             -->
<!-- ====================================================================== -->
<?php
if (file_exists(G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'translate_widget.php'))
{
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'translate_widget.php';
}
?>

<!-- ====================================================================== -->
<!-- JavaScript — jQuery 3.7 (CDN + Local Fallback)                         -->
<!-- 📖 Reference: https://jquery.com/download/                             -->
<!-- ====================================================================== -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script>
<script>
    // jQuery local fallback
    if (typeof jQuery === 'undefined') {
        document.write('<script src="/_libraries/jquery/jquery.min.js"><\/script>');
    }
</script>

<!-- ====================================================================== -->
<!-- JavaScript — Bootstrap 5.3 Bundle (CDN + Local Fallback)               -->
<!-- 📖 Reference: https://getbootstrap.com/docs/5.3/getting-started/       -->
<!-- ====================================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script>
    // Bootstrap JS local fallback
    if (typeof bootstrap === 'undefined') {
        document.write('<script src="/_libraries/bootstrap/js/bootstrap.bundle.min.js"><\/script>');
    }
</script>

<!-- ====================================================================== -->
<!-- Custom JavaScript (Component-specific, loaded if exists)               -->
<!-- ====================================================================== -->
<script src="/js/app.js" defer></script>
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

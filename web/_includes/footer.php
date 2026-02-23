<?php
/**
 * ============================================================================
 * 📄 GoToMyLink — HTML Footer Include
 * ============================================================================
 *
 * Closes the <main> element, outputs the footer with links, includes
 * JavaScript files (Bootstrap, jQuery, custom), cookie consent placeholder,
 * and debug panel (when G2ML_DEBUG is true).
 *
 * Dependencies: page_init.php (must be loaded first)
 *
 * @package    GoToMyLink
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
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
$siteName    = function_exists('getSetting') ? getSetting('site.name', 'GoToMyLink') : 'GoToMyLink';
?>

</main><!-- /#main-content -->

<!-- ====================================================================== -->
<!-- Footer                                                                 -->
<!-- ====================================================================== -->
<footer class="bg-dark text-light py-4 mt-auto" role="contentinfo">
    <div class="container">
        <div class="row">

            <!-- Column 1: Brand & Copyright -->
            <div class="col-md-4 mb-3">
                <h5><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></h5>
                <p class="text-muted small">
                    <?php echo function_exists('getSetting') ? htmlspecialchars(getSetting('site.tagline', 'Shorten. Track. Manage.'), ENT_QUOTES, 'UTF-8') : 'Shorten. Track. Manage.'; ?>
                </p>
                <p class="text-muted small mb-0">
                    &copy; <?php echo $currentYear; ?> MWBM Partners Ltd.
                    <?php echo function_exists('__') ? __('footer.rights') : 'All rights reserved.'; ?>
                </p>
            </div>

            <!-- Column 2: Quick Links -->
            <div class="col-md-4 mb-3">
                <h6><?php echo function_exists('__') ? __('footer.quick_links') : 'Quick Links'; ?></h6>
                <ul class="list-unstyled small">
                    <li><a href="/about" class="text-muted text-decoration-none"><?php echo function_exists('__') ? __('nav.about') : 'About'; ?></a></li>
                    <li><a href="/features" class="text-muted text-decoration-none"><?php echo function_exists('__') ? __('nav.features') : 'Features'; ?></a></li>
                    <li><a href="/pricing" class="text-muted text-decoration-none"><?php echo function_exists('__') ? __('nav.pricing') : 'Pricing'; ?></a></li>
                    <li><a href="/contact" class="text-muted text-decoration-none"><?php echo function_exists('__') ? __('footer.contact') : 'Contact'; ?></a></li>
                </ul>
            </div>

            <!-- Column 3: Legal -->
            <div class="col-md-4 mb-3">
                <h6><?php echo function_exists('__') ? __('footer.legal') : 'Legal'; ?></h6>
                <ul class="list-unstyled small">
                    <li><a href="/legal/terms" class="text-muted text-decoration-none"><?php echo function_exists('__') ? __('footer.terms') : 'Terms of Use'; ?></a></li>
                    <li><a href="/legal/privacy" class="text-muted text-decoration-none"><?php echo function_exists('__') ? __('footer.privacy') : 'Privacy Policy'; ?></a></li>
                    <li><a href="/legal/cookies" class="text-muted text-decoration-none"><?php echo function_exists('__') ? __('footer.cookies') : 'Cookie Policy'; ?></a></li>
                </ul>
            </div>

        </div>
    </div>
</footer>

<!-- ====================================================================== -->
<!-- Cookie Consent Placeholder (Phase 10)                                  -->
<!-- ====================================================================== -->
<!-- Cookie consent banner will be implemented in Phase 10 (Legal & Launch) -->

<!-- ====================================================================== -->
<!-- ARIA Live Region for Dynamic Status Updates                            -->
<!-- ====================================================================== -->
<?php echo function_exists('ariaLiveRegion') ? ariaLiveRegion('global-status', '', 'polite', 'status') : ''; ?>

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

        <?php if (!empty($debug['queries'])): ?>
        <details>
            <summary style="cursor:pointer;color:#0abde3;">Queries (<?php echo $debug['queryCount']; ?>)</summary>
            <table style="width:100%;border-collapse:collapse;margin-top:5px;">
                <thead>
                    <tr style="color:#feca57;">
                        <th style="text-align:left;padding:2px 5px;">#</th>
                        <th style="text-align:left;padding:2px 5px;">SQL</th>
                        <th style="text-align:right;padding:2px 5px;">Time</th>
                        <th style="text-align:center;padding:2px 5px;">OK</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($debug['queries'] as $i => $q): ?>
                    <tr style="border-top:1px solid #333;">
                        <td style="padding:2px 5px;color:#666;"><?php echo $i + 1; ?></td>
                        <td style="padding:2px 5px;max-width:600px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="<?php echo htmlspecialchars($q['sql'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(substr($q['sql'], 0, 120), ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td style="padding:2px 5px;text-align:right;"><?php echo $q['duration']; ?>ms</td>
                        <td style="padding:2px 5px;text-align:center;"><?php echo $q['success'] ? '✅' : '❌'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <?php endif; ?>
    </div>
    <?php
}
?>

</body>
</html>

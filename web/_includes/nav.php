<?php
/**
 * ============================================================================
 * 🧭 GoToMyLink — Navigation Bar Include
 * ============================================================================
 *
 * Responsive Bootstrap 5 navbar with i18n support and login state placeholder.
 * Adapts content based on the current component (A, B, C, Admin).
 *
 * Dependencies: page_init.php, accessibility.php, i18n.php
 *
 * @package    GoToMyLink
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - Bootstrap Navbar: https://getbootstrap.com/docs/5.3/components/navbar/
 *     - WCAG Navigation:  https://www.w3.org/TR/WCAG21/#multiple-ways
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

// ============================================================================
// 📋 Navigation Configuration
// ============================================================================
$component    = defined('G2ML_COMPONENT') ? G2ML_COMPONENT : 'A';
$siteName     = function_exists('getSetting') ? getSetting('site.name', 'GoToMyLink') : 'GoToMyLink';
$isLoggedIn   = isset($_SESSION['user_uid']) && $_SESSION['user_uid'] > 0;
$currentRoute = function_exists('getCurrentRoute') ? getCurrentRoute() : '';
?>

<!-- ====================================================================== -->
<!-- Navigation                                                             -->
<!-- 📖 Reference: https://getbootstrap.com/docs/5.3/components/navbar/     -->
<!-- ====================================================================== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary" aria-label="<?php echo function_exists('__') ? __('nav.aria_label') : 'Main navigation'; ?>">
    <div class="container">

        <!-- Brand -->
        <a class="navbar-brand" href="/">
            <strong><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></strong>
        </a>

        <!-- Mobile toggle button -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false"
                aria-label="<?php echo function_exists('__') ? __('nav.toggle') : 'Toggle navigation'; ?>">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible content -->
        <div class="collapse navbar-collapse" id="mainNavbar">

            <?php if ($component === 'A' || $component === 'Admin'): ?>
            <!-- ========================================================== -->
            <!-- Component A (Main Website) + Admin Navigation              -->
            <!-- ========================================================== -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?php echo ($currentRoute === '' ? ' active' : ''); ?>"
                       <?php echo ($currentRoute === '' ? 'aria-current="page"' : ''); ?>
                       href="/">
                        <i class="fas fa-home" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('nav.home') : 'Home'; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo ($currentRoute === 'about' ? ' active' : ''); ?>"
                       <?php echo ($currentRoute === 'about' ? 'aria-current="page"' : ''); ?>
                       href="/about">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('nav.about') : 'About'; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo ($currentRoute === 'features' ? ' active' : ''); ?>"
                       <?php echo ($currentRoute === 'features' ? 'aria-current="page"' : ''); ?>
                       href="/features">
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('nav.features') : 'Features'; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php echo ($currentRoute === 'pricing' ? ' active' : ''); ?>"
                       <?php echo ($currentRoute === 'pricing' ? 'aria-current="page"' : ''); ?>
                       href="/pricing">
                        <i class="fas fa-tags" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('nav.pricing') : 'Pricing'; ?>
                    </a>
                </li>
            </ul>
            <?php endif; ?>

            <!-- Right-aligned items -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php
                // Language switcher (included inline)
                if (file_exists(G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'language_switcher.php'))
                {
                    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'language_switcher.php';
                }
                ?>

                <?php if ($isLoggedIn): ?>
                <!-- Logged-in user menu (placeholder — implemented in Phase 5) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userMenuDropdown"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($_SESSION['user_display_name'] ?? 'Account', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                        <li><a class="dropdown-item" href="https://admin.go2my.link/">
                            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('nav.dashboard') : 'Dashboard'; ?>
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout">
                            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('nav.logout') : 'Log Out'; ?>
                        </a></li>
                    </ul>
                </li>
                <?php else: ?>
                <!-- Login/Register links (placeholder — implemented in Phase 5) -->
                <li class="nav-item">
                    <a class="nav-link" href="/login">
                        <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('nav.login') : 'Log In'; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-light btn-sm ms-2 mt-1 mt-lg-0" href="/register">
                        <?php echo function_exists('__') ? __('nav.register') : 'Sign Up'; ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Main content area (target for skip-to-content link) -->
<main id="main-content" tabindex="-1">

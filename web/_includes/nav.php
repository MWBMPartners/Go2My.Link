<?php
/**
 * ============================================================================
 * 🧭 Go2My.Link — Navigation Bar Include
 * ============================================================================
 *
 * Responsive Bootstrap 5 navbar with i18n support and login state placeholder.
 * Adapts content based on the current component (A, B, C, Admin).
 *
 * Dependencies: page_init.php, accessibility.php, i18n.php
 *
 * @package    Go2My.Link
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 2 (theme toggle Phase 3, auth dropdown Phase 4, org link Phase 5, privacy Phase 6)
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
$siteName     = function_exists('getSetting') ? getSetting('site.name', 'Go2My.Link') : 'Go2My.Link';
$isLoggedIn   = isset($_SESSION['user_uid']) && $_SESSION['user_uid'] > 0;
$currentRoute = function_exists('getCurrentRoute') ? getCurrentRoute() : '';
?>

<!-- ====================================================================== -->
<!-- Navigation                                                             -->
<!-- 📖 Reference: https://getbootstrap.com/docs/5.3/components/navbar/     -->
<!-- ====================================================================== -->
<nav class="navbar navbar-expand-lg bg-primary" data-bs-theme="dark" aria-label="<?php echo function_exists('__') ? __('nav.aria_label') : 'Main navigation'; ?>">
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

                <!-- ====================================================== -->
                <!-- 🎨 Theme Toggle (auto / light / dark)                  -->
                <!-- 📖 Reference: https://getbootstrap.com/docs/5.3/customize/color-modes/ -->
                <!-- ====================================================== -->
                <li class="nav-item">
                    <button type="button"
                            class="btn btn-link nav-link border-0"
                            id="g2ml-theme-toggle"
                            aria-label="<?php echo function_exists('__') ? __('nav.theme_toggle') : 'Toggle theme (light/dark/auto)'; ?>"
                            title="<?php echo function_exists('__') ? __('nav.theme_toggle') : 'Toggle theme'; ?>">
                        <i id="g2ml-theme-icon" class="fas fa-circle-half-stroke" aria-hidden="true"></i>
                        <span class="visually-hidden" id="g2ml-theme-label"><?php echo function_exists('__') ? __('theme.auto') : 'Auto (system)'; ?></span>
                    </button>
                </li>

                <?php
                // Language switcher (included inline)
                if (file_exists(G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'language_switcher.php'))
                {
                    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'language_switcher.php';
                }
                ?>

                <?php if ($isLoggedIn): ?>
                <!-- ====================================================== -->
                <!-- 👤 Logged-in User Dropdown                             -->
                <!-- ====================================================== -->
                <?php
                $userAvatar      = $_SESSION['user_avatar'] ?? '';
                $userDisplayName = $_SESSION['user_display_name'] ?? 'Account';
                $userEmail       = $_SESSION['user_email'] ?? '';
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenuDropdown"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if ($userAvatar !== ''): ?>
                        <img src="<?php echo g2ml_sanitiseOutput($userAvatar); ?>"
                             alt="" class="rounded-circle me-1" width="24" height="24"
                             style="object-fit:cover;">
                        <?php else: ?>
                        <i class="fas fa-user-circle me-1" aria-hidden="true"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                        <!-- User info header -->
                        <li class="dropdown-header">
                            <strong><?php echo htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($userEmail !== ''): ?>
                            <br><small class="text-body-secondary"><?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </li>
                        <li><hr class="dropdown-divider"></li>

                        <!-- Dashboard -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/">
                            <i class="fas fa-tachometer-alt fa-fw" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('nav.dashboard') : 'Dashboard'; ?>
                        </a></li>

                        <!-- My Links -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/links">
                            <i class="fas fa-link fa-fw" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('nav.my_links') : 'My Links'; ?>
                        </a></li>

                        <!-- Organisation -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/org">
                            <i class="fas fa-building fa-fw" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('nav.organisation') : 'Organisation'; ?>
                        </a></li>

                        <!-- Privacy & Data -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/privacy">
                            <i class="fas fa-shield-halved fa-fw" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('nav.privacy') : 'Privacy & Data'; ?>
                        </a></li>

                        <!-- Profile -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/profile">
                            <i class="fas fa-user-cog fa-fw" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('nav.profile') : 'Profile'; ?>
                        </a></li>

                        <li><hr class="dropdown-divider"></li>

                        <!-- Log Out -->
                        <li><a class="dropdown-item" href="/logout">
                            <i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('nav.logout') : 'Log Out'; ?>
                        </a></li>
                    </ul>
                </li>
                <?php else: ?>
                <!-- ====================================================== -->
                <!-- 🔐 Login / Register Links                              -->
                <!-- ====================================================== -->
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

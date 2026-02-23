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
if (defined('G2ML_COMPONENT')) {
    $component = G2ML_COMPONENT;
} else {
    $component = 'A';
}
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
$isLoggedIn   = isset($_SESSION['user_uid']) && $_SESSION['user_uid'] > 0;
if (function_exists('getCurrentRoute')) {
    $currentRoute = getCurrentRoute();
} else {
    $currentRoute = '';
}
?>

<!-- ====================================================================== -->
<!-- Navigation                                                             -->
<!-- 📖 Reference: https://getbootstrap.com/docs/5.3/components/navbar/     -->
<!-- ====================================================================== -->
<header>
<nav class="navbar navbar-expand-lg bg-primary" data-bs-theme="dark" aria-label="<?php if (function_exists('__')) { echo __('nav.aria_label'); } else { echo 'Main navigation'; } ?>">
    <div class="container">

        <!-- Brand -->
        <a class="navbar-brand" href="/">
            <strong><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></strong>
        </a>

        <!-- Mobile toggle button -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false"
                aria-label="<?php if (function_exists('__')) { echo __('nav.toggle'); } else { echo 'Toggle navigation'; } ?>">
            <span class="navbar-toggler-icon" aria-hidden="true"></span>
        </button>

        <!-- Collapsible content -->
        <div class="collapse navbar-collapse" id="mainNavbar">

            <?php if ($component === 'A' || $component === 'Admin') { ?>
            <!-- ========================================================== -->
            <!-- Component A (Main Website) + Admin Navigation              -->
            <!-- ========================================================== -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?php if ($currentRoute === '') { echo ' active'; } ?>"
                       <?php if ($currentRoute === '') { echo 'aria-current="page"'; } ?>
                       href="/">
                        <i class="fas fa-home" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('nav.home'); } else { echo 'Home'; } ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if ($currentRoute === 'about') { echo ' active'; } ?>"
                       <?php if ($currentRoute === 'about') { echo 'aria-current="page"'; } ?>
                       href="/about">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('nav.about'); } else { echo 'About'; } ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if ($currentRoute === 'features') { echo ' active'; } ?>"
                       <?php if ($currentRoute === 'features') { echo 'aria-current="page"'; } ?>
                       href="/features">
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('nav.features'); } else { echo 'Features'; } ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if ($currentRoute === 'pricing') { echo ' active'; } ?>"
                       <?php if ($currentRoute === 'pricing') { echo 'aria-current="page"'; } ?>
                       href="/pricing">
                        <i class="fas fa-tags" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('nav.pricing'); } else { echo 'Pricing'; } ?>
                    </a>
                </li>
            </ul>
            <?php } ?>

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
                            aria-label="<?php if (function_exists('__')) { echo __('nav.theme_toggle'); } else { echo 'Toggle theme (light/dark/auto)'; } ?>"
                            title="<?php if (function_exists('__')) { echo __('nav.theme_toggle'); } else { echo 'Toggle theme'; } ?>">
                        <i id="g2ml-theme-icon" class="fas fa-circle-half-stroke" aria-hidden="true"></i>
                        <span class="visually-hidden" id="g2ml-theme-label"><?php if (function_exists('__')) { echo __('theme.auto'); } else { echo 'Auto (system)'; } ?></span>
                    </button>
                </li>

                <?php
                // Language switcher (included inline)
                if (file_exists(G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'language_switcher.php'))
                {
                    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'language_switcher.php';
                }
                ?>

                <?php if ($isLoggedIn) { ?>
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
                        <?php if ($userAvatar !== '') { ?>
                        <img src="<?php echo g2ml_sanitiseOutput($userAvatar); ?>"
                             alt="" class="rounded-circle me-1" width="24" height="24"
                             style="object-fit:cover;">
                        <?php } else { ?>
                        <i class="fas fa-user-circle me-1" aria-hidden="true"></i>
                        <?php } ?>
                        <?php echo htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                        <!-- User info header -->
                        <li class="dropdown-header">
                            <strong><?php echo htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($userEmail !== '') { ?>
                            <br><small class="text-body-secondary"><?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php } ?>
                        </li>
                        <li><hr class="dropdown-divider"></li>

                        <!-- Dashboard -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/">
                            <i class="fas fa-tachometer-alt fa-fw" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('nav.dashboard'); } else { echo 'Dashboard'; } ?>
                        </a></li>

                        <!-- My Links -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/links">
                            <i class="fas fa-link fa-fw" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('nav.my_links'); } else { echo 'My Links'; } ?>
                        </a></li>

                        <!-- Organisation -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/org">
                            <i class="fas fa-building fa-fw" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('nav.organisation'); } else { echo 'Organisation'; } ?>
                        </a></li>

                        <!-- Privacy & Data -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/privacy">
                            <i class="fas fa-shield-halved fa-fw" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('nav.privacy'); } else { echo 'Privacy & Data'; } ?>
                        </a></li>

                        <!-- Profile -->
                        <li><a class="dropdown-item" href="https://admin.go2my.link/profile">
                            <i class="fas fa-user-cog fa-fw" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('nav.profile'); } else { echo 'Profile'; } ?>
                        </a></li>

                        <li><hr class="dropdown-divider"></li>

                        <!-- Log Out -->
                        <li><a class="dropdown-item" href="/logout">
                            <i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('nav.logout'); } else { echo 'Log Out'; } ?>
                        </a></li>
                    </ul>
                </li>
                <?php } else { ?>
                <!-- ====================================================== -->
                <!-- 🔐 Login / Register Links                              -->
                <!-- ====================================================== -->
                <li class="nav-item">
                    <a class="nav-link" href="/login">
                        <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('nav.login'); } else { echo 'Log In'; } ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-light btn-sm ms-2 mt-1 mt-lg-0" href="/register">
                        <?php if (function_exists('__')) { echo __('nav.register'); } else { echo 'Sign Up'; } ?>
                    </a>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</nav>
</header>

<!-- Main content area (target for skip-to-content link) -->
<main id="main-content" tabindex="-1">

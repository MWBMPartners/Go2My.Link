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
 * 🧭 Go2My.Link — 404 Not Found Error Page (Component A)
 * ============================================================================
 *
 * Branded error page for routes that do not resolve to a page.
 * Renders inside the full template (header/nav/footer).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 6 (#114)
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('error.404_title');
} else {
    $pageTitle = '404 — Page Not Found';
}
if (function_exists('__')) {
    $pageDesc = __('error.404_description');
} else {
    $pageDesc = 'The page you are looking for could not be found.';
}

http_response_code(404);
?>

<section class="py-5" aria-labelledby="error-heading">
    <div class="container" style="max-width:600px;">
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-map-signs fa-4x text-secondary" aria-hidden="true"></i>
            </div>
            <h1 id="error-heading" class="display-4 fw-bold mb-3">404</h1>
            <h2 class="h4 mb-3">
                <?php if (function_exists('__')) { echo __('error.404_heading'); } else { echo 'Page Not Found'; } ?>
            </h2>
            <p class="text-body-secondary mb-4">
                <?php if (function_exists('__')) { echo __('error.404_message'); } else { echo 'The page you are looking for could not be found. It may have been moved or deleted.'; } ?>
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('error.go_home'); } else { echo 'Go Home'; } ?>
                </a>
                <a href="/contact" class="btn btn-outline-secondary">
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('error.contact_us'); } else { echo 'Contact Us'; } ?>
                </a>
            </div>
        </div>
    </div>
</section>

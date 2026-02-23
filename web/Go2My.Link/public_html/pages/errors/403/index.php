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
 * 🔒 Go2My.Link — 403 Forbidden Error Page (Component A)
 * ============================================================================
 *
 * Branded error page for access denied / forbidden requests.
 * Renders inside the full template (header/nav/footer).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('error.403_title');
} else {
    $pageTitle = '403 — Forbidden';
}
if (function_exists('__')) {
    $pageDesc = __('error.403_description');
} else {
    $pageDesc = 'You do not have permission to access this resource.';
}

http_response_code(403);
?>

<section class="py-5" aria-labelledby="error-heading">
    <div class="container" style="max-width:600px;">
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-lock fa-4x text-danger" aria-hidden="true"></i>
            </div>
            <h1 id="error-heading" class="display-4 fw-bold mb-3">403</h1>
            <h2 class="h4 mb-3">
                <?php if (function_exists('__')) { echo __('error.403_heading'); } else { echo 'Access Denied'; } ?>
            </h2>
            <p class="text-body-secondary mb-4">
                <?php if (function_exists('__')) { echo __('error.403_message'); } else { echo 'You don\'t have permission to access this page. If you believe this is an error, please contact support.'; } ?>
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('error.go_home'); } else { echo 'Go Home'; } ?>
                </a>
                <a href="/login" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('nav.login'); } else { echo 'Log In'; } ?>
                </a>
            </div>
        </div>
    </div>
</section>

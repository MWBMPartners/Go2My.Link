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
 * ⚠️ Go2My.Link — 400 Bad Request Error Page (Component A)
 * ============================================================================
 *
 * Branded error page for malformed or invalid requests.
 * Renders inside the full template (header/nav/footer).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('error.400_title');
} else {
    $pageTitle = '400 — Bad Request';
}
if (function_exists('__')) {
    $pageDesc = __('error.400_description');
} else {
    $pageDesc = 'The request could not be understood by the server.';
}

http_response_code(400);
?>

<section class="py-5" aria-labelledby="error-heading">
    <div class="container" style="max-width:600px;">
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-exclamation-circle fa-4x text-warning" aria-hidden="true"></i>
            </div>
            <h1 id="error-heading" class="display-4 fw-bold mb-3">400</h1>
            <h2 class="h4 mb-3">
                <?php if (function_exists('__')) { echo __('error.400_heading'); } else { echo 'Bad Request'; } ?>
            </h2>
            <p class="text-body-secondary mb-4">
                <?php if (function_exists('__')) { echo __('error.400_message'); } else { echo 'The server could not understand your request. Please check the URL and try again.'; } ?>
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

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
 * 🔒 Go2My.Link — Data Export (Admin Dashboard)
 * ============================================================================
 *
 * Allows the user to request a portable export of all personal data held
 * by Go2My.Link, as provided under GDPR Article 20 (Right to Data
 * Portability) and CCPA (Right to Know).
 *
 * The export is generated as a JSON file and made available for download
 * for a limited time (default 48 hours).
 *
 * @package    Go2My.Link
 * @subpackage AdminDashboard
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

requireAuth();

if (function_exists('__')) {
    $pageTitle = __('export.title');
} else {
    $pageTitle = 'Export Your Data';
}
if (function_exists('__')) {
    $pageDesc = __('export.description');
} else {
    $pageDesc = 'Request a copy of your personal data.';
}

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Handle export request (POST)
// ============================================================================

$actionSuccess = '';
$actionError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'data_export'))
    {
        if (function_exists('__')) {
            $actionError = __('export.error_csrf');
        } else {
            $actionError = 'Session expired. Please try again.';
        }
    }
    else
    {
        $result = g2ml_requestDataExport($userUID);

        if ($result['success'])
        {
            if (function_exists('__')) {
                $actionSuccess = __('export.success');
            } else {
                $actionSuccess = 'Your data export has been generated. You can download it below.';
            }
        }
        else
        {
            if (function_exists('__')) {
                $errorMsg = __('export.error_generic');
            } else {
                $errorMsg = 'Failed to generate export. Please try again later.';
            }
            $actionError = g2ml_sanitiseOutput($result['error'] ?? $errorMsg);
        }
    }
}

// ============================================================================
// Fetch existing data requests (filtered to export type)
// ============================================================================

if (function_exists('g2ml_getUserDataRequests')) {
    $allRequests = g2ml_getUserDataRequests($userUID);
} else {
    $allRequests = [];
}
$exportRequests = array_filter($allRequests, fn(array $r) => ($r['requestType'] ?? '') === 'export');

// Check for a pending or processing export
$pendingExport = null;

foreach ($exportRequests as $req)
{
    if (in_array($req['status'] ?? '', ['pending', 'processing'], true))
    {
        $pendingExport = $req;
        break;
    }
}

// Check for a completed export with a valid download link
$downloadableExport = null;

foreach ($exportRequests as $req)
{
    if (($req['status'] ?? '') === 'completed'
        && !empty($req['exportExpiresAt'])
        && strtotime($req['exportExpiresAt']) > time())
    {
        $downloadableExport = $req;
        break;
    }
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="export-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/privacy"><?php if (function_exists('__')) { echo __('export.breadcrumb_privacy'); } else { echo 'Privacy & Data'; } ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php if (function_exists('__')) { echo __('export.breadcrumb_export'); } else { echo 'Export Your Data'; } ?></li>
            </ol>
        </nav>

        <h1 id="export-heading" class="h2 mb-4">
            <i class="fas fa-download" aria-hidden="true"></i>
            <?php if (function_exists('__')) { echo __('export.heading'); } else { echo 'Export Your Data'; } ?>
        </h1>

        <p class="text-body-secondary mb-4">
            <?php if (function_exists('__')) { echo __('export.intro'); } else { echo 'Under data protection regulations such as GDPR (Article 20) and CCPA, you have the right to receive a copy of all personal data we hold about you in a structured, commonly used, and machine-readable format.'; } ?>
        </p>

        <!-- Alerts -->
        <?php if ($actionSuccess !== '') { ?>
        <div class="alert alert-success alert-dismissible fade show" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php if (function_exists('__')) { echo __('export.close'); } else { echo 'Close'; } ?>"></button>
        </div>
        <?php } ?>

        <?php if ($actionError !== '') { ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo $actionError; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php if (function_exists('__')) { echo __('export.close'); } else { echo 'Close'; } ?>"></button>
        </div>
        <?php } ?>

        <!-- ============================================================== -->
        <!-- What's Included                                                 -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('export.whats_included'); } else { echo 'What\'s Included'; } ?>
                </h2>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><?php if (function_exists('__')) { echo __('export.includes_profile'); } else { echo 'Your profile information (name, email, timezone, account dates)'; } ?></li>
                    <li><?php if (function_exists('__')) { echo __('export.includes_links'); } else { echo 'All short URLs you have created (codes, destinations, click counts, dates)'; } ?></li>
                    <li><?php if (function_exists('__')) { echo __('export.includes_consent'); } else { echo 'Cookie consent records (type, decision, method, dates)'; } ?></li>
                    <li><?php if (function_exists('__')) { echo __('export.includes_sessions'); } else { echo 'Login sessions (device info, dates — tokens excluded for security)'; } ?></li>
                </ul>
                <p class="text-body-secondary small mt-2 mb-0">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('export.format_note'); } else { echo 'The export is provided as a JSON file. Download links expire after 48 hours for security.'; } ?>
                </p>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Export Actions                                                  -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-cloud-download-alt" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('export.action_heading'); } else { echo 'Request or Download'; } ?>
                </h2>
            </div>
            <div class="card-body">

                <?php if ($pendingExport !== null) { ?>
                <!-- Pending export -->
                <div class="alert alert-info mb-0" role="status">
                    <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('export.pending_message'); } else { echo 'Your data export is currently being prepared. Please check back shortly.'; } ?>
                    <br>
                    <small class="text-body-secondary">
                        <?php if (function_exists('__')) { echo __('export.requested_at'); } else { echo 'Requested'; } ?>:
                        <?php if (!empty($pendingExport['createdAt'])) { ?>
                        <time datetime="<?php echo g2ml_sanitiseOutput($pendingExport['createdAt']); ?>">
                            <?php echo date('j M Y, H:i', strtotime($pendingExport['createdAt'])); ?>
                        </time>
                        <?php } ?>
                    </small>
                </div>

                <?php } elseif ($downloadableExport !== null) { ?>
                <!-- Download available -->
                <div class="alert alert-success mb-3" role="status">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('export.download_ready'); } else { echo 'Your data export is ready for download.'; } ?>
                </div>

                <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                    <a href="/privacy/export?download=<?php echo (int) $downloadableExport['requestUID']; ?>"
                       class="btn btn-success">
                        <i class="fas fa-download" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('export.download_button'); } else { echo 'Download Export'; } ?>
                    </a>
                    <div class="text-body-secondary small">
                        <p class="mb-1">
                            <strong><?php if (function_exists('__')) { echo __('export.generated'); } else { echo 'Generated'; } ?>:</strong>
                            <?php if (!empty($downloadableExport['processedAt'])) { ?>
                            <time datetime="<?php echo g2ml_sanitiseOutput($downloadableExport['processedAt']); ?>">
                                <?php echo date('j M Y, H:i', strtotime($downloadableExport['processedAt'])); ?>
                            </time>
                            <?php } ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            <strong><?php if (function_exists('__')) { echo __('export.expires'); } else { echo 'Expires'; } ?>:</strong>
                            <time datetime="<?php echo g2ml_sanitiseOutput($downloadableExport['exportExpiresAt']); ?>">
                                <?php echo date('j M Y, H:i', strtotime($downloadableExport['exportExpiresAt'])); ?>
                            </time>
                        </p>
                    </div>
                </div>

                <hr>

                <!-- Allow requesting a new export even if one exists -->
                <p class="text-body-secondary small mb-2">
                    <?php if (function_exists('__')) { echo __('export.request_new_note'); } else { echo 'Need a fresh copy? You can request a new export below.'; } ?>
                </p>
                <form action="/privacy/export" method="POST" class="d-inline">
                    <?php echo g2ml_csrfField('data_export'); ?>
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-redo" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('export.request_new'); } else { echo 'Request New Export'; } ?>
                    </button>
                </form>

                <?php } else { ?>
                <!-- No export — show request button -->
                <p class="mb-3">
                    <?php if (function_exists('__')) { echo __('export.no_export_message'); } else { echo 'You have not requested a data export yet. Click the button below to generate a copy of all your data.'; } ?>
                </p>
                <form action="/privacy/export" method="POST">
                    <?php echo g2ml_csrfField('data_export'); ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('export.request_button'); } else { echo 'Request Export'; } ?>
                    </button>
                </form>
                <?php } ?>

            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Export Request History                                           -->
        <!-- ============================================================== -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-history" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('export.history_heading'); } else { echo 'Export History'; } ?>
                </h2>
            </div>
            <div class="card-body p-0">
                <?php if (empty($exportRequests)) { ?>
                <div class="p-4 text-center text-body-secondary">
                    <i class="fas fa-inbox fa-2x mb-2" aria-hidden="true"></i>
                    <p class="mb-0">
                        <?php if (function_exists('__')) { echo __('export.no_history'); } else { echo 'No export requests yet.'; } ?>
                    </p>
                </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" aria-label="<?php if (function_exists('__')) { echo __('export.history_table_label'); } else { echo 'Export request history'; } ?>">
                        <thead>
                            <tr>
                                <th scope="col"><?php if (function_exists('__')) { echo __('export.col_status'); } else { echo 'Status'; } ?></th>
                                <th scope="col"><?php if (function_exists('__')) { echo __('export.col_requested'); } else { echo 'Requested'; } ?></th>
                                <th scope="col"><?php if (function_exists('__')) { echo __('export.col_completed'); } else { echo 'Completed'; } ?></th>
                                <th scope="col"><?php if (function_exists('__')) { echo __('export.col_expires'); } else { echo 'Expires'; } ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exportRequests as $request) { ?>
                            <tr>
                                <td>
                                    <?php
                                    echo match ($request['status'] ?? '') {
                                        'pending'    => '<span class="badge bg-warning text-dark">Pending</span>',
                                        'processing' => '<span class="badge bg-info text-dark">Processing</span>',
                                        'completed'  => '<span class="badge bg-success">Completed</span>',
                                        'rejected'   => '<span class="badge bg-secondary">Cancelled</span>',
                                        default      => '<span class="badge bg-secondary">' . g2ml_sanitiseOutput($request['status'] ?? '') . '</span>',
                                    };
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($request['createdAt'])) { ?>
                                    <time datetime="<?php echo g2ml_sanitiseOutput($request['createdAt']); ?>">
                                        <?php echo date('j M Y, H:i', strtotime($request['createdAt'])); ?>
                                    </time>
                                    <?php } else { ?>
                                    &mdash;
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($request['processedAt'])) { ?>
                                    <time datetime="<?php echo g2ml_sanitiseOutput($request['processedAt']); ?>">
                                        <?php echo date('j M Y, H:i', strtotime($request['processedAt'])); ?>
                                    </time>
                                    <?php } else { ?>
                                    &mdash;
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($request['exportExpiresAt'])) { ?>
                                    <?php
                                    $isExpired = strtotime($request['exportExpiresAt']) < time();
                                    ?>
                                    <time datetime="<?php echo g2ml_sanitiseOutput($request['exportExpiresAt']); ?>"
                                          class="<?php if ($isExpired) { echo 'text-body-secondary text-decoration-line-through'; } ?>">
                                        <?php echo date('j M Y, H:i', strtotime($request['exportExpiresAt'])); ?>
                                    </time>
                                    <?php if ($isExpired) { ?>
                                    <span class="badge bg-secondary ms-1"><?php if (function_exists('__')) { echo __('export.expired'); } else { echo 'Expired'; } ?></span>
                                    <?php } ?>
                                    <?php } else { ?>
                                    &mdash;
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>

        <!-- Back link -->
        <div class="mt-4">
            <a href="/privacy" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('export.back_privacy'); } else { echo 'Back to Privacy & Data'; } ?>
            </a>
        </div>
    </div>
</section>

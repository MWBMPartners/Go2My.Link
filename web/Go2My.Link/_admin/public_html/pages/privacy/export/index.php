<?php
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

$pageTitle = function_exists('__') ? __('export.title') : 'Export Your Data';
$pageDesc  = function_exists('__') ? __('export.description') : 'Request a copy of your personal data.';

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
        $actionError = function_exists('__')
            ? __('export.error_csrf')
            : 'Session expired. Please try again.';
    }
    else
    {
        $result = g2ml_requestDataExport($userUID);

        if ($result['success'])
        {
            $actionSuccess = function_exists('__')
                ? __('export.success')
                : 'Your data export has been generated. You can download it below.';
        }
        else
        {
            $actionError = g2ml_sanitiseOutput($result['error'] ?? (function_exists('__')
                ? __('export.error_generic')
                : 'Failed to generate export. Please try again later.'));
        }
    }
}

// ============================================================================
// Fetch existing data requests (filtered to export type)
// ============================================================================

$allRequests    = function_exists('g2ml_getUserDataRequests') ? g2ml_getUserDataRequests($userUID) : [];
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
                <li class="breadcrumb-item"><a href="/privacy"><?php echo function_exists('__') ? __('export.breadcrumb_privacy') : 'Privacy & Data'; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo function_exists('__') ? __('export.breadcrumb_export') : 'Export Your Data'; ?></li>
            </ol>
        </nav>

        <h1 id="export-heading" class="h2 mb-4">
            <i class="fas fa-download" aria-hidden="true"></i>
            <?php echo function_exists('__') ? __('export.heading') : 'Export Your Data'; ?>
        </h1>

        <p class="text-body-secondary mb-4">
            <?php echo function_exists('__')
                ? __('export.intro')
                : 'Under data protection regulations such as GDPR (Article 20) and CCPA, you have the right to receive a copy of all personal data we hold about you in a structured, commonly used, and machine-readable format.'; ?>
        </p>

        <!-- Alerts -->
        <?php if ($actionSuccess !== '') { ?>
        <div class="alert alert-success alert-dismissible fade show" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo function_exists('__') ? __('export.close') : 'Close'; ?>"></button>
        </div>
        <?php } ?>

        <?php if ($actionError !== '') { ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo $actionError; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo function_exists('__') ? __('export.close') : 'Close'; ?>"></button>
        </div>
        <?php } ?>

        <!-- ============================================================== -->
        <!-- What's Included                                                 -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('export.whats_included') : 'What\'s Included'; ?>
                </h2>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><?php echo function_exists('__') ? __('export.includes_profile') : 'Your profile information (name, email, timezone, account dates)'; ?></li>
                    <li><?php echo function_exists('__') ? __('export.includes_links') : 'All short URLs you have created (codes, destinations, click counts, dates)'; ?></li>
                    <li><?php echo function_exists('__') ? __('export.includes_consent') : 'Cookie consent records (type, decision, method, dates)'; ?></li>
                    <li><?php echo function_exists('__') ? __('export.includes_sessions') : 'Login sessions (device info, dates — tokens excluded for security)'; ?></li>
                </ul>
                <p class="text-body-secondary small mt-2 mb-0">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <?php echo function_exists('__')
                        ? __('export.format_note')
                        : 'The export is provided as a JSON file. Download links expire after 48 hours for security.'; ?>
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
                    <?php echo function_exists('__') ? __('export.action_heading') : 'Request or Download'; ?>
                </h2>
            </div>
            <div class="card-body">

                <?php if ($pendingExport !== null) { ?>
                <!-- Pending export -->
                <div class="alert alert-info mb-0" role="status">
                    <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <?php echo function_exists('__')
                        ? __('export.pending_message')
                        : 'Your data export is currently being prepared. Please check back shortly.'; ?>
                    <br>
                    <small class="text-body-secondary">
                        <?php echo function_exists('__') ? __('export.requested_at') : 'Requested'; ?>:
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
                    <?php echo function_exists('__')
                        ? __('export.download_ready')
                        : 'Your data export is ready for download.'; ?>
                </div>

                <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                    <a href="/privacy/export?download=<?php echo (int) $downloadableExport['requestUID']; ?>"
                       class="btn btn-success">
                        <i class="fas fa-download" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('export.download_button') : 'Download Export'; ?>
                    </a>
                    <div class="text-body-secondary small">
                        <p class="mb-1">
                            <strong><?php echo function_exists('__') ? __('export.generated') : 'Generated'; ?>:</strong>
                            <?php if (!empty($downloadableExport['processedAt'])) { ?>
                            <time datetime="<?php echo g2ml_sanitiseOutput($downloadableExport['processedAt']); ?>">
                                <?php echo date('j M Y, H:i', strtotime($downloadableExport['processedAt'])); ?>
                            </time>
                            <?php } ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            <strong><?php echo function_exists('__') ? __('export.expires') : 'Expires'; ?>:</strong>
                            <time datetime="<?php echo g2ml_sanitiseOutput($downloadableExport['exportExpiresAt']); ?>">
                                <?php echo date('j M Y, H:i', strtotime($downloadableExport['exportExpiresAt'])); ?>
                            </time>
                        </p>
                    </div>
                </div>

                <hr>

                <!-- Allow requesting a new export even if one exists -->
                <p class="text-body-secondary small mb-2">
                    <?php echo function_exists('__')
                        ? __('export.request_new_note')
                        : 'Need a fresh copy? You can request a new export below.'; ?>
                </p>
                <form action="/privacy/export" method="POST" class="d-inline">
                    <?php echo g2ml_csrfField('data_export'); ?>
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-redo" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('export.request_new') : 'Request New Export'; ?>
                    </button>
                </form>

                <?php } else { ?>
                <!-- No export — show request button -->
                <p class="mb-3">
                    <?php echo function_exists('__')
                        ? __('export.no_export_message')
                        : 'You have not requested a data export yet. Click the button below to generate a copy of all your data.'; ?>
                </p>
                <form action="/privacy/export" method="POST">
                    <?php echo g2ml_csrfField('data_export'); ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('export.request_button') : 'Request Export'; ?>
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
                    <?php echo function_exists('__') ? __('export.history_heading') : 'Export History'; ?>
                </h2>
            </div>
            <div class="card-body p-0">
                <?php if (empty($exportRequests)) { ?>
                <div class="p-4 text-center text-body-secondary">
                    <i class="fas fa-inbox fa-2x mb-2" aria-hidden="true"></i>
                    <p class="mb-0">
                        <?php echo function_exists('__')
                            ? __('export.no_history')
                            : 'No export requests yet.'; ?>
                    </p>
                </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" aria-label="<?php echo function_exists('__') ? __('export.history_table_label') : 'Export request history'; ?>">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo function_exists('__') ? __('export.col_status') : 'Status'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('export.col_requested') : 'Requested'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('export.col_completed') : 'Completed'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('export.col_expires') : 'Expires'; ?></th>
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
                                          class="<?php echo $isExpired ? 'text-body-secondary text-decoration-line-through' : ''; ?>">
                                        <?php echo date('j M Y, H:i', strtotime($request['exportExpiresAt'])); ?>
                                    </time>
                                    <?php if ($isExpired) { ?>
                                    <span class="badge bg-secondary ms-1"><?php echo function_exists('__') ? __('export.expired') : 'Expired'; ?></span>
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
                <?php echo function_exists('__') ? __('export.back_privacy') : 'Back to Privacy & Data'; ?>
            </a>
        </div>
    </div>
</section>

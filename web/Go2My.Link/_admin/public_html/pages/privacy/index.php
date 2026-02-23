<?php
/**
 * ============================================================================
 * 🔒 Go2My.Link — Privacy & Data Dashboard (Admin Dashboard)
 * ============================================================================
 *
 * Privacy dashboard overview page. Shows cookie consent status, data export
 * option, and account deletion option as card summaries. Below the cards,
 * a "Recent Requests" table lists the user's data subject requests.
 *
 * @package    Go2My.Link
 * @subpackage AdminDashboard
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

requireAuth();

$pageTitle = function_exists('__') ? __('privacy.title') : 'Privacy & Data';
$pageDesc  = function_exists('__') ? __('privacy.description') : 'Manage your privacy settings, cookie preferences, and data rights.';

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Fetch consent summary and recent data requests
// ============================================================================

$consentSummary = function_exists('g2ml_getConsentSummary') ? g2ml_getConsentSummary() : [];
$dataRequests   = function_exists('g2ml_getUserDataRequests') ? g2ml_getUserDataRequests($userUID) : [];

/**
 * Helper: format a consent status value into a human-readable badge.
 *
 * @param  bool|null $status  true = consented, false = refused, null = not set
 * @return string             HTML badge markup
 */
function _privacyConsentBadge(?bool $status): string
{
    if ($status === true)
    {
        return '<span class="badge bg-success"><i class="fas fa-check-circle" aria-hidden="true"></i> '
            . (function_exists('__') ? __('privacy.consent_granted') : 'Granted') . '</span>';
    }

    if ($status === false)
    {
        return '<span class="badge bg-secondary"><i class="fas fa-times-circle" aria-hidden="true"></i> '
            . (function_exists('__') ? __('privacy.consent_refused') : 'Refused') . '</span>';
    }

    return '<span class="badge bg-warning text-dark"><i class="fas fa-question-circle" aria-hidden="true"></i> '
        . (function_exists('__') ? __('privacy.consent_not_set') : 'Not Set') . '</span>';
}

/**
 * Helper: format a data request status into a coloured badge.
 *
 * @param  string $status  Request status from tblDataDeletionRequests
 * @return string          HTML badge markup
 */
function _privacyRequestStatusBadge(string $status): string
{
    return match ($status) {
        'pending'    => '<span class="badge bg-warning text-dark">Pending</span>',
        'processing' => '<span class="badge bg-info text-dark">Processing</span>',
        'completed'  => '<span class="badge bg-success">Completed</span>',
        'rejected'   => '<span class="badge bg-secondary">Cancelled</span>',
        default      => '<span class="badge bg-secondary">' . g2ml_sanitiseOutput($status) . '</span>',
    };
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-heading">
    <div class="container">
        <h1 id="privacy-heading" class="h2 mb-4">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            <?php echo function_exists('__') ? __('privacy.heading') : 'Privacy & Data'; ?>
        </h1>

        <p class="text-body-secondary mb-4">
            <?php echo function_exists('__')
                ? __('privacy.intro')
                : 'Manage your cookie preferences, request a copy of your data, or delete your account. These rights are provided under applicable data protection laws including GDPR and CCPA.'; ?>
        </p>

        <!-- ============================================================== -->
        <!-- Privacy Action Cards                                            -->
        <!-- ============================================================== -->
        <div class="row g-4 mb-5" role="group" aria-label="<?php echo function_exists('__') ? __('privacy.actions_label') : 'Privacy actions'; ?>">

            <!-- Card 1: Cookie Preferences -->
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3">
                            <i class="fas fa-cookie-bite text-primary" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('privacy.cookies_title') : 'Cookie Preferences'; ?>
                        </h2>
                        <p class="text-body-secondary small mb-3">
                            <?php echo function_exists('__')
                                ? __('privacy.cookies_desc')
                                : 'Control which types of cookies you allow on this site.'; ?>
                        </p>

                        <?php if (!empty($consentSummary)) { ?>
                        <ul class="list-unstyled small mb-3">
                            <li class="mb-1">
                                <strong><?php echo function_exists('__') ? __('privacy.essential') : 'Essential'; ?>:</strong>
                                <?php echo _privacyConsentBadge($consentSummary['essential'] ?? true); ?>
                            </li>
                            <li class="mb-1">
                                <strong><?php echo function_exists('__') ? __('privacy.analytics') : 'Analytics'; ?>:</strong>
                                <?php echo _privacyConsentBadge($consentSummary['analytics'] ?? null); ?>
                            </li>
                            <li class="mb-1">
                                <strong><?php echo function_exists('__') ? __('privacy.functional') : 'Functional'; ?>:</strong>
                                <?php echo _privacyConsentBadge($consentSummary['functional'] ?? null); ?>
                            </li>
                            <li class="mb-1">
                                <strong><?php echo function_exists('__') ? __('privacy.marketing') : 'Marketing'; ?>:</strong>
                                <?php echo _privacyConsentBadge($consentSummary['marketing'] ?? null); ?>
                            </li>
                        </ul>
                        <?php } ?>

                        <a href="/privacy/consent" class="btn btn-outline-primary btn-sm">
                            <?php echo function_exists('__') ? __('privacy.manage_cookies') : 'Manage Cookies'; ?>
                            <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 2: Data Export -->
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3">
                            <i class="fas fa-download text-primary" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('privacy.export_title') : 'Data Export'; ?>
                        </h2>
                        <p class="text-body-secondary small mb-3">
                            <?php echo function_exists('__')
                                ? __('privacy.export_desc')
                                : 'You have the right to receive a copy of all personal data we hold about you. Request an export in a machine-readable JSON format.'; ?>
                        </p>
                        <a href="/privacy/export" class="btn btn-outline-primary btn-sm">
                            <?php echo function_exists('__') ? __('privacy.request_export') : 'Request Export'; ?>
                            <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 3: Delete Account -->
            <div class="col-md-4">
                <div class="card shadow-sm h-100 border-danger">
                    <div class="card-body">
                        <h2 class="h5 mb-3 text-danger">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('privacy.delete_title') : 'Delete Account'; ?>
                        </h2>
                        <p class="text-body-secondary small mb-3">
                            <?php echo function_exists('__')
                                ? __('privacy.delete_desc')
                                : 'You have the right to request permanent deletion of your account and all associated data. This action cannot be undone after the grace period.'; ?>
                        </p>
                        <a href="/privacy/delete" class="btn btn-outline-danger btn-sm">
                            <?php echo function_exists('__') ? __('privacy.delete_account') : 'Delete Account'; ?>
                            <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Recent Data Requests                                            -->
        <!-- ============================================================== -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-history" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('privacy.recent_requests') : 'Recent Requests'; ?>
                </h2>
            </div>
            <div class="card-body p-0">
                <?php if (empty($dataRequests)) { ?>
                <div class="p-4 text-center text-body-secondary">
                    <i class="fas fa-inbox fa-2x mb-2" aria-hidden="true"></i>
                    <p class="mb-0">
                        <?php echo function_exists('__')
                            ? __('privacy.no_requests')
                            : 'You have not made any data requests yet.'; ?>
                    </p>
                </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" aria-label="<?php echo function_exists('__') ? __('privacy.requests_table_label') : 'Data requests'; ?>">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo function_exists('__') ? __('privacy.col_type') : 'Type'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('privacy.col_status') : 'Status'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('privacy.col_requested') : 'Requested'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('privacy.col_processed') : 'Processed'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dataRequests as $request) { ?>
                            <tr>
                                <td>
                                    <?php
                                    $typeLabel = match ($request['requestType'] ?? '') {
                                        'export'   => function_exists('__') ? __('privacy.type_export') : 'Data Export',
                                        'deletion' => function_exists('__') ? __('privacy.type_deletion') : 'Account Deletion',
                                        default    => g2ml_sanitiseOutput($request['requestType'] ?? 'Unknown'),
                                    };
                                    echo $typeLabel;
                                    ?>
                                </td>
                                <td><?php echo _privacyRequestStatusBadge($request['status'] ?? 'unknown'); ?></td>
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
            <a href="/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('privacy.back_dashboard') : 'Back to Dashboard'; ?>
            </a>
        </div>
    </div>
</section>

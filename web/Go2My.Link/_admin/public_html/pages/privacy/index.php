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

if (function_exists('__')) {
    $pageTitle = __('privacy.title');
} else {
    $pageTitle = 'Privacy & Data';
}
if (function_exists('__')) {
    $pageDesc = __('privacy.description');
} else {
    $pageDesc = 'Manage your privacy settings, cookie preferences, and data rights.';
}

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Fetch consent summary and recent data requests
// ============================================================================

if (function_exists('g2ml_getConsentSummary')) {
    $consentSummary = g2ml_getConsentSummary();
} else {
    $consentSummary = [];
}
if (function_exists('g2ml_getUserDataRequests')) {
    $dataRequests = g2ml_getUserDataRequests($userUID);
} else {
    $dataRequests = [];
}

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
        if (function_exists('__')) {
            $consentLabel = __('privacy.consent_granted');
        } else {
            $consentLabel = 'Granted';
        }
        return '<span class="badge bg-success"><i class="fas fa-check-circle" aria-hidden="true"></i> '
            . $consentLabel . '</span>';
    }

    if ($status === false)
    {
        if (function_exists('__')) {
            $consentLabel = __('privacy.consent_refused');
        } else {
            $consentLabel = 'Refused';
        }
        return '<span class="badge bg-secondary"><i class="fas fa-times-circle" aria-hidden="true"></i> '
            . $consentLabel . '</span>';
    }

    if (function_exists('__')) {
        $consentLabel = __('privacy.consent_not_set');
    } else {
        $consentLabel = 'Not Set';
    }
    return '<span class="badge bg-warning text-dark"><i class="fas fa-question-circle" aria-hidden="true"></i> '
        . $consentLabel . '</span>';
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
            <?php if (function_exists('__')) { echo __('privacy.heading'); } else { echo 'Privacy & Data'; } ?>
        </h1>

        <p class="text-body-secondary mb-4">
            <?php if (function_exists('__')) { echo __('privacy.intro'); } else { echo 'Manage your cookie preferences, request a copy of your data, or delete your account. These rights are provided under applicable data protection laws including GDPR and CCPA.'; } ?>
        </p>

        <!-- ============================================================== -->
        <!-- Privacy Action Cards                                            -->
        <!-- ============================================================== -->
        <div class="row g-4 mb-5" role="group" aria-label="<?php if (function_exists('__')) { echo __('privacy.actions_label'); } else { echo 'Privacy actions'; } ?>">

            <!-- Card 1: Cookie Preferences -->
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3">
                            <i class="fas fa-cookie-bite text-primary" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('privacy.cookies_title'); } else { echo 'Cookie Preferences'; } ?>
                        </h2>
                        <p class="text-body-secondary small mb-3">
                            <?php if (function_exists('__')) { echo __('privacy.cookies_desc'); } else { echo 'Control which types of cookies you allow on this site.'; } ?>
                        </p>

                        <?php if (!empty($consentSummary)) { ?>
                        <ul class="list-unstyled small mb-3">
                            <li class="mb-1">
                                <strong><?php if (function_exists('__')) { echo __('privacy.essential'); } else { echo 'Essential'; } ?>:</strong>
                                <?php echo _privacyConsentBadge($consentSummary['essential'] ?? true); ?>
                            </li>
                            <li class="mb-1">
                                <strong><?php if (function_exists('__')) { echo __('privacy.analytics'); } else { echo 'Analytics'; } ?>:</strong>
                                <?php echo _privacyConsentBadge($consentSummary['analytics'] ?? null); ?>
                            </li>
                            <li class="mb-1">
                                <strong><?php if (function_exists('__')) { echo __('privacy.functional'); } else { echo 'Functional'; } ?>:</strong>
                                <?php echo _privacyConsentBadge($consentSummary['functional'] ?? null); ?>
                            </li>
                            <li class="mb-1">
                                <strong><?php if (function_exists('__')) { echo __('privacy.marketing'); } else { echo 'Marketing'; } ?>:</strong>
                                <?php echo _privacyConsentBadge($consentSummary['marketing'] ?? null); ?>
                            </li>
                        </ul>
                        <?php } ?>

                        <a href="/privacy/consent" class="btn btn-outline-primary btn-sm">
                            <?php if (function_exists('__')) { echo __('privacy.manage_cookies'); } else { echo 'Manage Cookies'; } ?>
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
                            <?php if (function_exists('__')) { echo __('privacy.export_title'); } else { echo 'Data Export'; } ?>
                        </h2>
                        <p class="text-body-secondary small mb-3">
                            <?php if (function_exists('__')) { echo __('privacy.export_desc'); } else { echo 'You have the right to receive a copy of all personal data we hold about you. Request an export in a machine-readable JSON format.'; } ?>
                        </p>
                        <a href="/privacy/export" class="btn btn-outline-primary btn-sm">
                            <?php if (function_exists('__')) { echo __('privacy.request_export'); } else { echo 'Request Export'; } ?>
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
                            <?php if (function_exists('__')) { echo __('privacy.delete_title'); } else { echo 'Delete Account'; } ?>
                        </h2>
                        <p class="text-body-secondary small mb-3">
                            <?php if (function_exists('__')) { echo __('privacy.delete_desc'); } else { echo 'You have the right to request permanent deletion of your account and all associated data. This action cannot be undone after the grace period.'; } ?>
                        </p>
                        <a href="/privacy/delete" class="btn btn-outline-danger btn-sm">
                            <?php if (function_exists('__')) { echo __('privacy.delete_account'); } else { echo 'Delete Account'; } ?>
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
                    <?php if (function_exists('__')) { echo __('privacy.recent_requests'); } else { echo 'Recent Requests'; } ?>
                </h2>
            </div>
            <div class="card-body p-0">
                <?php if (empty($dataRequests)) { ?>
                <div class="p-4 text-center text-body-secondary">
                    <i class="fas fa-inbox fa-2x mb-2" aria-hidden="true"></i>
                    <p class="mb-0">
                        <?php if (function_exists('__')) { echo __('privacy.no_requests'); } else { echo 'You have not made any data requests yet.'; } ?>
                    </p>
                </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" aria-label="<?php if (function_exists('__')) { echo __('privacy.requests_table_label'); } else { echo 'Data requests'; } ?>">
                        <thead>
                            <tr>
                                <th scope="col"><?php if (function_exists('__')) { echo __('privacy.col_type'); } else { echo 'Type'; } ?></th>
                                <th scope="col"><?php if (function_exists('__')) { echo __('privacy.col_status'); } else { echo 'Status'; } ?></th>
                                <th scope="col"><?php if (function_exists('__')) { echo __('privacy.col_requested'); } else { echo 'Requested'; } ?></th>
                                <th scope="col"><?php if (function_exists('__')) { echo __('privacy.col_processed'); } else { echo 'Processed'; } ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dataRequests as $request) { ?>
                            <tr>
                                <td>
                                    <?php
                                    if (function_exists('__')) {
                                        $labelExport = __('privacy.type_export');
                                        $labelDeletion = __('privacy.type_deletion');
                                    } else {
                                        $labelExport = 'Data Export';
                                        $labelDeletion = 'Account Deletion';
                                    }
                                    $typeLabel = match ($request['requestType'] ?? '') {
                                        'export'   => $labelExport,
                                        'deletion' => $labelDeletion,
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
                <?php if (function_exists('__')) { echo __('privacy.back_dashboard'); } else { echo 'Back to Dashboard'; } ?>
            </a>
        </div>
    </div>
</section>
